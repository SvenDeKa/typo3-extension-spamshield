      #
      # Table structure for table 'tx_spamshield_log'
      #
      CREATE TABLE tx_spamshield_log (
          uid int(11) NOT NULL auto_increment,
          pid int(11) DEFAULT '0' NOT NULL,
          tstamp int(11) DEFAULT '0' NOT NULL,
          crdate int(11) DEFAULT '0' NOT NULL,
          cruser_id int(11) DEFAULT '0' NOT NULL,
          deleted tinyint(4) DEFAULT '0' NOT NULL,
          spamweight int(11) DEFAULT '0' NOT NULL,
          spamreason text NOT NULL,
          requesturl text NOT NULL,
          pageid tinyint(5) DEFAULT '0' NOT NULL,
          postvalues text NOT NULL,
          getvalues text NOT NULL,
          ip tinytext NOT NULL,
          useragent tinytext NOT NULL,
          referer tinytext NOT NULL,
          solved tinyint(4) DEFAULT '0' NOT NULL,

          PRIMARY KEY (uid),
          KEY parent (pid)
      );
