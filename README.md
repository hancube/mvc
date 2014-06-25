###############
Before Using
###############
1) Mysql PDO Required

###############
How To Use
###############
1) Open /config/config.php, and set the information
2) Create a table in your MySql Database
CREATE TABLE `Sample` (
`Id` int(11) NOT NULL AUTO_INCREMENT,
`Sample` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
`CreateDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`Id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
3) Run on Your Browser
YOUR_APP_HOME/?c=sample&a=add&sample=Test
YOUR_APP_HOME/?c=sample&a=get

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

###################################
Differences between 3.0.0 and 3.1.0
###################################
1. Added VERSION management for all features
    1) Added Version File
    2) Changed file names in lib
    3) Added Output version

############
Version Rule
############
1. Basic Version Policy
    1) New version contains all history of the old versions.
       You can use any version with the latest version.
       You can define MVC_VERSION and MVC_CODE_VERSION differently, but the MCV_CODE_VERSION must be higher than MVC_VERSION.
2. Version Format Policy
    Format: X1.X2.X3
    1) If X1 is different, you cannot just switch the version. you have to upgrade your front-end too.
    2) If X2 is different, something is improved or added. You can upgrade MVC without any change.
       (Only specify the version on Config.php)
    3) If X3 is different, some errors are fixed. You can also upgrade MVC version without any change.
       (Of course you have to specify the version, so the path target the new version folder)
