#!/usr/local/bin/python2.7
"""
    Copyright (c) 2016-2018 Ad Schellevis <ad@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    Aggregate flowd data for reporting
"""
import time
import os
import sys
import fcntl
import signal
import copy
import syslog
import traceback
import socket
import argparse
from lib import load_config
from lib.parse import parse_flow
from lib.aggregate import AggMetadata
import lib.aggregates


MAX_FILE_SIZE_MB = 10
MAX_LOGS = 10
SOCKET_PATH = "/var/run/flowd.socket"

def aggregate_flowd(config, server, do_vacuum=False):
    """ aggregate collected flowd data
    :param config: script configuration
    :param do_vacuum: vacuum database after cleanup
    :return: None
    """
    # init metadata (progress maintenance)
    metadata = AggMetadata(config.database_dir)

    # register aggregate classes to stream data to
    stream_agg_objects = list()
    for agg_class in lib.aggregates.get_aggregators():
        for resolution in agg_class.resolutions():
            stream_agg_objects.append(agg_class(resolution, config.database_dir))

    # parse flow data and stream to registered consumers
    commit_record_count = 0
    for flow_record in parse_flow(server):
        if (flow_record is None and commit_record_count > 0) or commit_record_count > 100000:
            # commit data on receive timestamp change or last record
            for stream_agg_object in stream_agg_objects:
                stream_agg_object.commit()
        if flow_record is not None:
            # send to aggregator
            for stream_agg_object in stream_agg_objects:
                # class add() may change the flow contents for processing, its better to isolate
                # paremeters here.
                flow_record_cpy = copy.copy(flow_record)
                stream_agg_object.add(flow_record_cpy)
            commit_record_count += 1

    # expire old data
    for stream_agg_object in stream_agg_objects:
        stream_agg_object.cleanup(do_vacuum)
        del stream_agg_object
    del metadata


class Main(object):
    config = None

    @classmethod
    def set_config(cls, config):
        cls.config = config

    def __init__(self):
        """ construct, hook signal handler and run aggregators
        :return: None
        """
        self.running = True
        signal.signal(signal.SIGTERM, self.signal_handler)
        self.run()

    def run(self):
        """ run, endless loop, until sigterm is received
        :return: None
        """
        # check database consistency / repair
        syslog.syslog(syslog.LOG_NOTICE, 'startup, check database.')
        check_and_repair('%s/*.sqlite' % self.config.database_dir)

        vacuum_interval = (60*60*8) # 8 hour vacuum cycle
        vacuum_countdown = None
        if os.path.exists(SOCKET_PATH):
            os.remove(SOCKET_PATH)
        server = socket.socket(socket.AF_UNIX, socket.SOCK_DGRAM)
        server.bind(SOCKET_PATH)
        syslog.syslog(syslog.LOG_NOTICE, 'start watching flowd')
        while self.running:
            # should we perform a vacuum
            if not vacuum_countdown or vacuum_countdown < time.time():
                vacuum_countdown = time.time() + vacuum_interval
                do_vacuum = True
            else:
                do_vacuum = False

            # run aggregate
            try:
                aggregate_flowd(self.config, server, do_vacuum)
                if do_vacuum:
                    syslog.syslog(syslog.LOG_NOTICE, 'vacuum done')
            except:
                syslog.syslog(syslog.LOG_ERR, 'flowd aggregate died with message %s' % (traceback.format_exc()))
                return
            # wait for next pass, exit on sigterm
            if Main.config.single_pass:
                break
            else:
                for i in range(30):
                    if self.running:
                        time.sleep(0.5)
                    else:
                        break

        server.close()
        os.remove(SOCKET_PATH)

    def signal_handler(self, sig, frame):
        """ end (run) loop on signal
        :param sig: signal
        :pram frame: frame
        :return: None
        """
        self.running = False


if __name__ == '__main__':
    # parse arguments and load config
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', help='configuration yaml', default=None)
    parser.add_argument('--console', dest='console', help='run in console', action='store_true')
    parser.add_argument('--profile', dest='profile', help='enable profiler', action='store_true')
    parser.add_argument('--repair', dest='repair', help='init repair', action='store_true')
    cmd_args = parser.parse_args()

    Main.set_config(
        load_config(cmd_args.config)
    )
    from sqlite3_helper import check_and_repair

    if cmd_args.console:
        # command line start
        if cmd_args.profile:
            # start with profiling
            import cProfile
            import io
            import pstats

            pr = cProfile.Profile(builtins=False)
            pr.enable()
            Main()
            pr.disable()
            s = io.StringIO()
            sortby = 'cumulative'
            ps = pstats.Stats(pr, stream=s).sort_stats(sortby)
            ps.print_stats()
            print s.getvalue()
        else:
            Main()
    elif cmd_args.repair:
        # force a database repair, when
        try:
            lck = open(Main.config.pid_filename, 'a+')
            fcntl.flock(lck, fcntl.LOCK_EX | fcntl.LOCK_NB)
            check_and_repair(filename_mask='%s/*.sqlite' % Main.config.database_dir, force_repair=True)
            lck.close()
            os.remove(Main.config.pid_filename)
        except IOError:
            # already running, exit status 99
            sys.exit(99)
    else:
        # Daemonize flowd aggregator
        from daemonize import Daemonize
        daemon = Daemonize(app="flowd_aggregate", pid=Main.config.pid_filename, action=Main)
        daemon.start()
