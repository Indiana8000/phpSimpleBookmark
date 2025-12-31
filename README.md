# phpSimpleBookmark
Simple Bookmark Manager for your local web server (e.g. Synology, QNAP, UGreen, ...)

Data is stored in a local json file, no database required. Images are stored in subfolders.

### Features
Graps Title, Description and FavIcon from the webpage. (Create new entry with only URL filled)

### Optional
You can use a screenshot server (see /server) for an aditional preview image.

## Warning!
No security checks implemented! Only use it in you local network!

URLs can be set to anything (e.g. pishing links) including Javascript which will be executed!

## Instalation
Just copy all files from *src* directory into any location at your htdoc/web folder.

Ensure that data.json and the uploads folder is writeable for the webserver.

### Optional

For multi user, just create an additional folder with all files.

You can secure it with Basic Authentification / htaccess.
