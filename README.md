Requirements
------------

* python 2.6 + pycassa, eventlet, MySQLdb
* php 5.3 + APC (optional)
* mysql >= 5.1
* cassandra >= 0.7b + matching thrift
* orbited >= 0.7.10
* JDK for compression scripts

Installation
------------

After installing necessary software:
1. Using MySql Workbench synchronize local db from ./docs/db\_model.mwb
2. Load scheme in Cassandra from ./config/cassandra.cli
3. Start Orbited with ./config/orbited.cfg
4. Init comet server `./comet/index.py --init`
5. Start comet server `./comet/index.py`
6. Edit ./config.php according your facebook and google accounts.

