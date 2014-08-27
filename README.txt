###############
Before Using
###############
1) Mysql PDO Required

###############
How To Use
###############
1) Open /config/config.php, and set the information
2) Create a table in your MySql Database
DROP TABLE IF EXISTS `Admins`;
CREATE TABLE IF NOT EXISTS `Admins` (
`Id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`userId` varchar(255) COLLATE utf8_bin NOT NULL,
`Name` varchar(255) COLLATE utf8_bin NOT NULL,
`CreateDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`Id`),
KEY `i_userId` (`userId`),
KEY `i_CreateDate` (`CreateDate`)
)  ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
DROP TABLE IF EXISTS `Permissions`; 
CREATE TABLE IF NOT EXISTS `Permissions` (
 `Id` int(11) NOT NULL AUTO_INCREMENT,
 `adminId` int(11) NOT NULL,
 `Controller` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
 `Action` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
 `Status` int(11) DEFAULT '0',
 `CreateDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`Id`),
 KEY `i_adminId` (`adminId`),
 KEY `i_Controller` (`Controller`),
 KEY `i_Action` (`Action`),
 KEY `i_Status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

3) Run on Your Browser
YOUR_API_HOME/?c=admin&a=add&name=Test
YOUR_API_HOME/?c=admin&a=get

###############
Default Inputs
###############
Parameter	 Null	 Desc	 Example
c	 Required	 Controller	 cate, child, album
a	 Required	 Action	 add, edit, del, get
f	 Optional	 Output format	 json, jsonp, xml
cb	 Optional	 Callback Function name (using only for jsonp format)	 callback (default)
s	 Optional	 Start	 0
l	 Optional	 Limit	 10
o	 Optional	 Oder by, you can use any field in the main table of the model	 id
d	 Optional	 Direction	 ASC, DESC

####################################
Output Format (OUTPUT_VERSION 2.0.0)
####################################
Array
(
  [result] => 0 or 1,
  [errors] => Array
      (
          [code] => ERROR_NO_CONTROLLER_CLASS,
          [text] => You have to do something,
          [fileds] => Array
             (
                 [filed1] => Array
                     (
                          [code] => ERROR_REQUIRED,
                          [text] => You have to do something
                     ),
             )
      ),
  [data] => Array
      (
          [info] => Array
             (
                  [total] => 10,
                  [count] => 5,
                  [id1] => 10,
             ),
          [items] => Array
             (
                 [0] => Array ([field1] => [value1], [field2] => [value2]),
                 [1] => Array ([field1] => [value1], [field2] => [value2])
             )
      )
)

<?xml version="1.0"?>
<root>
   <result>1</result>
   <data>
       <info>
           <total>10</total>
           <count>5</count>
       </info>
       <items>
           <item>
               <id>53</id>
               <userid>1</userid>
               <listid>80</listid>
           </item>
           <item>
               <id>44</id>
               <userid>1</userid>
               <listid>79</listid>
           </item>
       </items>
   </data>
</root>

