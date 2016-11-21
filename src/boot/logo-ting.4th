\ Copyright (c) 2003 Scott Long <scottl@FreeBSD.org>
\ Copyright (c) 2003 Aleksander Fafula <alex@fafula.com>
\ Copyright (c) 2006-2015 Devin Teske <dteske@FreeBSD.org>
\ All rights reserved.
\ 
\ Redistribution and use in source and binary forms, with or without
\ modification, are permitted provided that the following conditions
\ are met:
\ 1. Redistributions of source code must retain the above copyright
\    notice, this list of conditions and the following disclaimer.
\ 2. Redistributions in binary form must reproduce the above copyright
\    notice, this list of conditions and the following disclaimer in the
\    documentation and/or other materials provided with the distribution.
\ 
\ THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
\ ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
\ IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
\ ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
\ FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
\ DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
\ OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
\ HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
\ LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
\ OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
\ SUCH DAMAGE.
\ 
\ $FreeBSD$

46 logoX ! 1 logoY ! \ Initialize logo placement defaults

: logo+ ( x y c-addr/u -- x y' )
	2swap 2dup at-xy 2swap \ position the cursor
	[char] @ escc! \ replace @ with Esc
	type \ print to the screen
	1+ \ increase y for next time we're called
;

: logo ( x y -- ) \ color BSD mascot (24 rows x 34 columns)

	s"               @[32m                  " logo+
	s"                                 " logo+
	s"            ..  .......          " logo+
	s"         ..NM  MMMMMMMMM.        " logo+
	s"       ..MM.    .....  .MMM.     " logo+
	s"       MM.   MMMMMMMMM.   ^M     " logo+
	s"           MM.          .M       " logo+
	s"       MMM    MMMI MMM   .MM.    " logo+ 
	s"      MM   MMMM    .MMMM, .MM:   " logo+
	s"     MM. MMM.  IMMMM.  IMM  MMI  " logo+
	s"    MM    . :MMM^  ^MMM  MM  MM. " logo+
	s"   MM. MM. MM^       .MM  MM  MM " logo+
	s"      .M: MM. ,MMMMMM..MM  MM    " logo+
	s"      MM  MM .MM  .MM  .M7 MM.   " logo+
	s"      MM  MM MM.   ,M  .MM MM.   " logo+
	s"      MM. MM  MMN  .MM MM.       " logo+
	s"   .M      MM  .MM  .MMM. ,M     " logo+
	s"   .MM ~MM  MM8           MM  MM " logo+
	s"    MM: ~MM  ^MMMMMM    MMM  MM  " logo+
	s"     MMM  MMM,       .MMM.  MM   " logo+
	s"      .MM. .MMMMM  MMMM.   M7    " logo+
	s"        . M..  `^  `  .:M        " logo+
	s"          .MMMMMMMMMMMMM,.       " logo+
	s"             ``^:MMI^`@[m           " logo+

	2drop
;
