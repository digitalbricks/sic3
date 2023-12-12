## Setup SIC Satellite

### The manual way
For SIC to be able to request data from a site, you have to "install" the so called **SIC Satellite** on the server. The **SIC Satellite** is a single PHP script [available on Github](https://github.com/digitalbricks/sic-satellite), so "installing" means just copying that single file to the document root of your website.

But before you start, make sure your target system (CMS / Shop / Cloud) is supported: The SIC Satellite scripts supports some common Content Management and Shop Systems out of the box. Make sure the system you wanna track is part of the [currently supported list](https://github.com/digitalbricks/sic-satellite/blob/master/README.md#currently-supported-cms). It is? Fine! So remember the **System identifier** listed in the above linked table – you will need this later.

Great so far, now just download the `satellite.php` from [Github](https://github.com/digitalbricks/sic-satellite/blob/master/satellite.php) open the file in a editor of your choice and modify the `$sat_secret` value with a random string you choose. This is the **Shared Secret** which acts as a password for the SIC Satellite. The satellite script will not respond to requests not providing this secret.
You may also add some contact information in the comment area inside the satellite script (search for `[YOUR_CONTACT_INFORMATION]`) to let others know that this is a legit file from you.
Save the file and upload it to the document root of the website in question. Remember the **Shared Secret** (value of `$sat_secret`), you will also need that later.

Having done that, make sure that the satellite is accessible by URL: Just call `https://yourdomain.tdl/satellite.php` in your Browser. If the script is reachable, you should see the output `No valid output` in your browser window – which is totally fine at the moment. Copy the URL (the **SIC Satellite URL**) from your browsers address bar.

Now you should have the **System identifier**, the **SIC Satellite URL** and the **Shared Secret**. Insert this value into the SIC site configuration and save. No got to the SIC Dashboard and check if SIC is able to get the data from the satellite by clicking the Refresh button.

### Satellite Auto-Generation
SIC has a function, on the site edit screen, for generating the satellite PHP code for you, preconfigured with your shared secret and contact information (if set up under "Settings" menu). But this function is only available when the required site settings ( **Site name**, **System identifier**, **SIC Satellite URL** and **Shared Secret** ) are set up and saved initially. So you have to define the fields in advance.



