{% if helpers.exists('OPNsense.proxy.general.enabled') and OPNsense.proxy.general.enabled|default("0") == "1" %}
squid_enable=YES
squid_opnsense_bootup_run="/usr/local/opnsense/scripts/proxy/setup.sh"
squid_krb5_ktname="/usr/local/etc/squid/squid.keytab"
{% else %}
squid_enable=NO
{% endif %}
