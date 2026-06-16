## SIC3 Addons available since 3.4.0
Since version 3.4.0 SIC has basic built in support for addons/plugins.
There is a demo addon "Helloworld" included, which can be used as a template for creating your own addons.

Addons can be disabled by either adding a dot (.) in front of the addon folder name, or by adding 
an underscore (_) at the end of the addon folder name. For example, if you have an addon called "MyAddon", 
you can disable it by renaming the folder to ".MyAddon" or "MyAddon\_".

Please note that addons have complete access to the SIC API and the underlying F3 API, so be cautious when installing 
addons from untrusted sources. Always review the code of an addon before using it to ensure it does not contain 
any malicious or harmful code. And do your backups :-)