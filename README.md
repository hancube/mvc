/**
 * How To Use
 *
 * 1) Open /config/config.php, and set the information
 * 2) Create a table in your MySql Database
 * CREATE TABLE `Sample` (
 * `Id` int(11) NOT NULL AUTO_INCREMENT,
 * `Sample` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
 * `CreateDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 * PRIMARY KEY (`Id`)
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
 *
 * 3) Run on Your Browser
 * YOUR_APP_HOME/?c=sample&a=add&sample=Test
 * YOUR_APP_HOME/?c=sample&a=get
 *
 * Differences between 2.1.8 and 3.0.0
 * 1) Output Format with Error Messages
 * 2) Where Config
 */
