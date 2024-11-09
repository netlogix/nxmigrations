CREATE TABLE `sys_doctrine_migrationstatus` (
    version varchar(255) NOT NULL,
    executed_at datetime,
    execution_time integer,

    PRIMARY KEY (version)
) ENGINE=InnoDB;
