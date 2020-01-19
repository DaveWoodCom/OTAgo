
![OTAgo][otago-logo]

[![badge-language]][php.net]
[![badge-license]][license]

[![badge-mastodon]][mastodon-davewoodx]
[![badge-twitter]][twitter-davewoodx]

[![badge-sponsors]][cerebral-gardens]
[![badge-patreon]][patreon-davewoodx]

## About

OTAgo is an OTA app distribution system that allows you and your users to securely install their iOS apps over the air (OTA) using Apple's officially supported method [documented here](https://support.apple.com/en-ca/guide/deployment-reference-ios/apda0e3426d7/web).

## Communication

* If you need help, use [Stack Overflow][stackoverflow] (Tag '[otago][stackoverflow]').
* If you'd like to ask a general question, use [Stack Overflow][stackoverflow].
* If you've found a bug, open an issue.
* If you have a feature request, open an issue.
* If you want to contribute, submit a pull request.
* If you use OTAgo, please Star the project on [GitHub][github-otago]

## Requirements

* HTTPS enabled web server ([nginx][nginx] or [Apache][apache] recommended)
* PHP 7.x
* .ipa file signed with an ad-hoc or enterprise distribution profile

## How to Use

Clone the repo into a folder accessible via HTTPS. You must use HTTPS with a valid (not self-signed) SSL/TLS certificate. (I recommend [Let's Encrypt][letsencrypt]). 

### Configuration

Edit the file `configuration.php` file.

`$authFile` -> filename of a `.php` file to handle the authentication (see below).  
`$webTemplate` -> the `.html` template to be displayed to the user before they install the app.  
`$installURLPlacehHolder` -> a placeholder token for the link that will start the app installation.  
`$manifestTemplate` -> the `manifest.plist` template used to install the app.  
`$ipaURLPlacehHolder` -> a placeholder token in the above manifest template file where the authenticated URL will be swapped in.  
`$ipaFile` -> the `.ipa` file signed for distribution.  
`$baseURL` -> During an OTA installation, some files need to be referenced by their full URL. OTAgo uses a default value for the baseURL, however it's not likely going to match your actual URL, so you'll want to set this directly.  

The files above do not need to be located in a publicly accessible folder, their contents will be served by the OTAgo scripts.

### AuthFile

The `$authFile` variable above needs to name a file that can be included by the OTAgo scripts. This allows you to sub in different methods of authentication, a simple list of username/passwords, connect to an external database, or use OAuth. Currently OTAgo includes a `simpleAuth.php` file that handles a basic list of username/password pairs.

If you wish to use another authentication method, you need to create an alternate authFile that includes the following methods:


```php
	function isValidUser($username, $password)
```

This takes in a username and password and must return `true` if they the user is authorized to install the app.


```php
	function requestAuthentication()
```

This method takes no arguments, and returns no value. It must send whatever is needed to the client to deny access and request authorization. Like the version in the `simpleAuth.php` file, you can call `requestBasicAuthentication()` to trigger a BASIC authentication request.


### Templates

The `$webTemplate` file needs to be an HTML web page that will be displayed to the user. This can be as simple a single link, or more complicated with details about the app with instructions for the user on how to install it (trusting the Enterprise certificate for example). The template file itself does not need to be in a publicly accessible folder, however any files the page links to, images, stylesheets, etc must be. The `$webTemplate` file should have at least one link with the `href` set to the `$installURLPlacehHolder` (`{{InstallURL}}` in our demo). That link will start the install process when the user taps it.

The `$manifestTemplate` file needs to be a valid `manifest.plist` file (see [Apple's documentation](https://support.apple.com/en-ca/guide/deployment-reference-ios/apd11fd167c4/web) for specifics), but instead of specifying the URL for the `.ipa` file, use the `$ipaURLPlacehHolder` placeholder (`{{IPAURL}}` in our demo). The authenticated URL will be substituted into the `.plist` file before it's sent to the user's device.

### Contributing

OTAgo can only exist with support from the community. There are many ways you can help continue to make it great.

* Star the project on [GitHub][github-otago].  
* Report issues/bugs you find.  
* Suggest features.  
* Submit pull requests.  
* Download and install one of my apps: [https://www.cerebralgardens.com/apps/][cerebral-gardens-apps]. Try my newest app: [All the Rings][all-the-rings].  
* You can visit my [Patreon][patreon-davewoodx] and contribute financially.  

**Note**: when submitting a pull request, please use lots of small commits verses one huge commit. It makes it much easier to merge in when there are several pull requests that need to be combined for a new version.

## Why's the project called OTAgo, and why is a Koala involved?

I'm personally very concerned about the planet and the current Climate Emergency we're in. During the time I've been developing this project, there have been massive bushfires in Australia. I wanted to name the project after the situation. There are several places in Australia named `Otago` which has an obvious link to `OTA`; it felt like a perfect name. The koala is a reference to the hundreds of thousands of animals killed during the fires.






[otago-logo]: https://github.com/DaveWoodCom/OTAgo/raw/master/logo.png
[php.net]: https://php.net/
[license]: https://github.com/DaveWoodCom/OTAgo/blob/master/LICENSE

[mastodon-davewoodx]: https://mastodon.social/@davewoodx
[twitter-davewoodx]: https://twitter.com/davewoodx

[stackoverflow]: https://stackoverflow.com/questions/tagged/otago

[cerebral-gardens]: https://www.cerebralgardens.com/
[cerebral-gardens-apps]: https://www.cerebralgardens.com/apps/
[all-the-rings]: https://alltherings.fit/?s=GH4
[patreon-davewoodx]: https://www.patreon.com/DaveWoodX

[badge-language]: https://img.shields.io/badge/PHP-7.x-blue.svg?style=flat
[badge-license]: https://img.shields.io/badge/License-BSD%203--Clause-blue.svg?style=flat

[badge-sponsors]: https://img.shields.io/badge/Sponsors-Cerebral%20Gardens-orange.svg?style=flat
[badge-mastodon]: https://img.shields.io/badge/Mastodon-DaveWoodX-606A84.svg?style=flat
[badge-twitter]: https://img.shields.io/twitter/follow/DaveWoodX.svg?style=social
[badge-patreon]: https://img.shields.io/badge/Patreon-DaveWoodX-F96854.svg?style=flat

[github-otago]: https://github.com/DaveWoodCom/OTAgo
[nginx]: https://nginx.org/
[apache]: https://httpd.apache.org/
[letsencrypt]: https://letsencrypt.org/

