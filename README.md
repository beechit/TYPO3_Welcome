Welcome to TYPO3 - TYPO3camp Venlo
==================================

This repository is used for the workshop 'Introduction building a website TYPO3' at http://www.typo3campvenlo.nl
Main keypoints of this workshop are:
* Installing TYPO3 by composer
* Installing the TYPO3 introduction package https://typo3.org/extensions/repository/view/introduction
* Adjusting the introduction package styling to your own
* Adding a news systems to your website https://github.com/TYPO3-extensions/news
* Creating a custom 'Blog' extension

TYPO3 System requirements
=========================

TYPO3 is based upon PHP and uses a MySQL database. For more information
regarding these requirements see the [INSTALL](https://github.com/TYPO3/TYPO3.CMS/blob/master/INSTALL.md) file.

Using the Database Abstraction Layer (DBAL) allows one to use TYPO3 with other
Database Management Systems, like PostgreSQL, Oracle and MSSQL.

Installing TYPO3 by composer
============================

Installation of TYPO3 can easily done by [composer][]. A quick way to install TYPO3 can be used by create a project with
the TYPO3 Base distribution:

    composer create-project typo3/cms-base-distribution projectname

More information about how to install TYPO3 by composer can be found at https://composer.typo3.org

[composer]: https://getcomposer.org/ "The PHP package manager"

Setting up the TYPO3 website
----------------------------

The installation of TYPO3 succeeded and now it is time to setup/configure TYPO3. TYPO3 is shipped with a setup tool which performs the necessary steps to full fill the configuration. If you visit your installation now in the browser you see a message thanking you for downloading TYPO3. In here it will also be mentioned to create a file 'FIRST_INSTALL' to continue.

Create a file in your webdir of your installationm on the terminal hit the following command:

    cd web; touch FIRST_INSTALL;

Visit the website again in the browser and walkthrough the onscreen steps in short:

1. System environment check
  Fix corresponding errors and continue
2. Database credentials
  - Create your database in phpmyadmin and make sure you database collation is set on 'utf8_general_ci'
  - Enter the credentials
3. Select your created database
4. Create user and import data
5. Optionally select a preconfigured website
  - For this workshop we select 'Do nothing, just get me to the Backend' because we install the distribution by composer.

Installing the TYPO3 introduction package / other extension
===========================================================

When visiting your website by the url gives you the error 'Service unavailable (503) - No pages are found on the rootlevel'. This is normal because we don't have installed the introduction package neither we have setup a single page we could view. When selecting the option 'Create a empty page' in step 5 of the configuration a single page should have be shown.
The introduction package we are going to install is an TYPO3 extension which we add to our installation. TYPO3 offers a lot of extensions you can see at TYPO3 Extension Repository (TER) at https://typo3.org/extensions/repository/ .
To install a extension just require the extension in your main composer by the followed command  (installing the introduction package):

    composer require typo3-ter/introduction

TYPO3 automatically places this extension in the correct extension directory ('web/typo3conf/ext/') were all third party and own extension will be. (When using a NOT composer based installation you can either download the files and place this into this directory OR use the Backend extensionmanager to download an extension from TER)

Last step is to active the extension (in this case the introduction package) in the Backend (BE) extension manager. When you now visit the website you will have you're first TYPO3 website running!

TYPO3 basics / common practises / structure of your TYPO3 installation
======================================================================

Before going to the next section *Personalize the introduction package* it is good to know some basics / common practises /
structure of your TYPO3 installation.

Definition list
---------------
Below here are some definitions that are quite often used and good to know ;)

**Rootpage**

The root of your website (presented with the globe-icon). (In the IP 'Congratulations');
	
**uid**

Unique identifier of a page/item/object

**pid**

Parent identifier of the current page/item/object

**TER**

The **T** YPO3 **E** xtension **R** epository which contains over 6000+ already available extensions free to use!

**BE**

TYPO3 Backend

**FE**

TYPO3 Frontend / your website

Structure of the BE
-------------------

The structure of the BE (after login at (*www.yourwebsite.com/***/typo3**)) is as follows:

1. Available backend modules
   - The page and list module you are going to use the most to control the content.
   - The template/extensions/install/configuration is mostly used to configure/control/develop/debug your website
2. Page tree (only visible in module under the category 'Web')
   - The different pages and folders/storages containing the content of your website
3. Content/adjust part
4. Tools for caching/searching and to adjust user settings

![TYPO3 Backend structure] (Images/typo3_backend.png)


Troubleshooting / tips
----------------------

Here are a list of common problems I experienced/experiencing during developing in TYPO3 and afterwards you think 'Of course!!'.

* Did you cleared the caches?!! (clear the caches in the top right cache menu)
* Is my setting really used and not overwritten somewhere?  In other words what is the loaded typoscript setting in the template object browser?
* Is the typoScript not been overwritten/ is it visible?
* Is the database up to date with my code (perform an database check in the install tool)
* Is the extension active / are the dependencies to other extension set in your ext_emconf.php and are those active?
* To be continued with 'AHA moments'

Minimal overview of a website structure in dirs
-----------------------------------------------

Directory of TYPO3 composer based:
```
├── vendor
├── web
│   ├── fileadmin
│   ├── typo3 (symlink to the vendor typo3 folder)
│   ├── typoconf
│   │   ├── ext (The extension directory)
│   │   ├── l10n (the language files downloaded for the BE)
│   │   ├── LocalConfiguration.php (Local configuration of the website e.g. database access)
│   │   ├── PackageStates.php (keeps track of the package/extension states (written by the extensionmanager))
│   ├── typo3temp (cached files)
│   ├── uploads (user uploads)
│   ├── .htaccess
│   ├── index.php (symlink to the vendor typo3 index which is your website startpoint)
├── composer.json
├── composer.lock

```

Commonly used directory structure for an extension

```
├── site_template
│   ├── Classes
│   │   ├── Command (command controllers that can invoke terminal commands)
│   │   ├── Controller (controllers for the FE/BE)
│   │   ├── Domain
│   │   │   ├── Model (Domain model objects)
│   │   │   ├── Repository (Repositories to the database (ORM mappers))
│   │   ├── ViewHelpers (Classes to help within the view)
│   ├── Configuration
│   │   ├── TCA (Table Configuration Array)( Object mapping between DB and model) (also config for the BE lists /edits)
│   │   ├── TypoScript
│   │   │   ├── [OPTIONAL] Other dirs e.g.
│   │   │   ├── constants.txt (typoscript constants)
│   │   │   ├── setup.txt (typoscript setup (includes the other files in the "other dirs")
│   ├── Resources
│   │   ├── Private
│   │   │   ├── Ext
│   │   │   │   ├── News
│   │   │   │   │   ├── Layouts
│   │   │   │   │   ├── Partials
│   │   │   │   │   ├── Templates
│   │   │   ├── Language (contains all languages and translations)
│   │   │   ├── [OPTIONAL] Less
│   │   │   ├── Layouts (The fluid layouts)
│   │   │   ├── Partials (The fluid partials)
│   │   │   ├── Templates (the fluid templates)
│   │   │   ├── .htaccess
│   │   ├── Public
│   │   │   ├── Css 
│   │   │   ├── Icons
│   │   │   ├── Images
│   │   │   ├── Fonts
│   │   │   ├── Javascript
│   ├── ext_icon.png
│   ├── ext_emconf.php
│   ├── ext_localconf.php
│   ├── ext_tables.php
│   ├── ext_tables.sql

```

Personalize the introduction package
====================================

Personalizing the website to your own preferences have to be done in your OWN extensions and **NOT!!** in thirds party extensions.
 Modifying those means updating those extensions (as bugfixes) is **HARD** and your code is mixed which is a **very bad situation** to have.
If there are bugs then clone the original repository fix it and notify *or even better provide a patch* to the original builder so that the extension can be updated and other can also have profit of your work.

Create a custom extension to adjust the introduction package:
-------------------------------------------------------------

The personalization of the website / introduction package will be done in a own TYPO3 extension 'site_template'. The minimal requirements for an own TYPO3 extension are:

* Directory with the name of the extension (site_template)
* ext_emconf.php

   This file is needed to recognize and activate your extension in the extensionmanager.
   See the file [ext_emconf.php] (/Files/ext_emconf.php)
   
* ext_icon.png (16px*16px) [ext_icon.png] (/Files/ext_icon.png)

If you go to the extension manager in the backend you will see your extension and can activate your extension.

Adjust the logo of the  introduction package
--------------------------------------------

In the introduction pacakge the logo is made configurable by TypoScript, to adjust this logo and logo properties (height/width/alt) we need to override the constants so that the correct file to our logo is used. If you look into the `constants.txt` of the extension bootstrap package you see the following information :

    page {
        logo {
            # cat=bootstrap package: basic/110/100; type=string; label=Logo: Leave blank to use website title from template instead
            file = EXT:bootstrap_package/Resources/Public/Images/BootstrapPackage.png
            # cat=bootstrap package: basic/110/110; type=int+; label=Height: The image will not be resized!
            height = 60
            # cat=bootstrap package: basic/110/120; type=int+; label=Width: The image will not be resized!
            width = 210
            # cat=bootstrap package: basic/110/130; type=string; label=Alternative text: Text of the alt attribute of the logo image (default: "<website title> logo")
            alt =
        }
    }
    
To override this create your own `setup.txt` (for later use) and `constants.txt` (see extension dir structure which directory). Copy those contents as above in the constants.txt and modify the file to the path to your own logo in the site_template extension. For example : `file = EXT:site_template/Resources/Public/Images/logo.png`. The `EXT:site_template` directs to your extension directory.

The TypoScript then needs to be included in your website. This needs a certain order otherwise because we want to override the bootstrap_package constants and not let the bootstrap constants override ours. Therefore we first need to mention it as an *static template* by adding the following lines to `ext_tables.php` . 

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        $_EXTKEY,
        'Configuration/TypoScript',
        'Site template (after bootstrap package)'
    );

After this we can include this *static template* to our website by: the following steps
 
- In the BE select Template module (BE structure 1)
- Select rootpage in the pageTree (Be structure 2)
- Select Info/modify (be structure 3 top left)
- Go to 'Edit the whole template record'
- Include your static in the tab 'includes'
- Adjust order that the site_template comes **after** bootstrap_package
- Save changes
- Clear cache

**In the 'info/modify' -> 'Edit the whole template record' TypoScript could be overwritten in the fields 'settings' or 'constants'.
The values can be deleted because we are going to set this in our site_template.**

(another way to navigate to the 'Edit the whole template record is: List Module -> Select rootpage -> edit Template)

The website logo should have been updated now.

####Verify correct override of the TypoScript constant

When you navigate to the Template module, select your root page in the pagetree (BE structure 2) and go to the Select option
'TypoScript Object browser' you can check under constants which settings are used.

Adjust the colors/css
---------------------

The default color scheme / css styling is perhaps not as desired. To adjust this in TYPO3 you can add your own css styles.

First step is to create an css file to adjust the color of the menu bar to the economical green color. This file is called
main.css and is located at Resources/Public/Css. Content of this file to change the menubar to gray is:

	.navbar {
		background-color: green;
	}

Second step is to configure/setup TYPO3 to include this file at every page. In the created `setup.txt` file you have to
 add the following line to include your main.css file.

	page.includeCSS.all = EXT:site_template/Resources/Public/Css/main.css

After clearing the cache, your menu bar has an economical greenish background color.

Add javascript
--------------

Adding javascript is almost similar as adjusting the color/css. You need to configure a typoscript setting to inform TYPO3 were to find your javascript and then add the javascript (`Resources/Public/Javascript/main.js`) file.

    # include javascript
    page.includeJSFooterlibs {
        main = EXT:site_template/Resources/Public/Javascript/main.js
        main.excludeFromConcatenation = 1
    }
    
Small javascript demo 

    $(document).ready(function () {
        $('body').append('<p class="javascripttext">TYPO3 workshop added javascript at <a href="http://www.typo3campvenlo.nl/" target="_blank">TYPO3camp Venlo</a></p>');
    });
    
Some small css for this to make it more visible:
    
    .javascripttext {
        text-align: center;
        background-color: #FF8600;
        color: #111111;
        min-height: 40px;
        padding: 10px;
    }
    .javascripttext a {
        color: #111111;
        font-weight: bold;
    }

Adjust templates
----------------

At this point you should be able to adjust the css/javascript of the website, next step is to adjust and create templates.

In very short TYPO3 uses the following three parts

* Templates
* Layouts
* Partials

Currently adjusting the templates of the bootstrap_package is already prepared by the bootstrap_package to define constants were TYPO3 can look for templates, if no templates are found matching the template name it fall backs to the default template(s) of the bootstrap_package. The following constants can be added to your `constants.txt` to provide the directory TYPO3 needs to look in our site_template.

    page {
        fluidtemplate {
    		# cat=Default Site Configuration (Beech.it site_template); type=string; label=Layout Root Path: Path to layouts
    		layoutRootPath = EXT:site_template/Resources/Private/Layouts
    		# cat=Default Site Configuration (Beech.it site_template); type=string; label=Partial Root Path: Path to partials
    		partialRootPath = EXT:site_template/Resources/Private/Partials
    		# cat=Default Site Configuration (Beech.it site_template); type=string; label=Template Root Path: Path to templates
    		templateRootPath = EXT:site_template/Resources/Private/Templates
    	}
    }

After this you can copy one of the templates of the bootstrap_package to your own directories and modify the content.

**Trouble shooting** 

* Template file name identical to bootstrap_package?
* Correct paths?
* Cache flushed?
* TypoScript constants not overwritten (see Template object browser)?

Create own templates
--------------------

*Requirements: The constants of the fluidtemplate are set in section 'adjust templates'*

Creating own templates need a BE configuration for the editor to have a visual overview were the content will be placed in the FE. The configuration of these template will be separated in different files to avoid a very large file and losing structure. Therefore first create a folder (e.g.`Configuration/BackendLayouts/`) which is going to contain a different file for every template you are going to create.

Create a file `Configuration/TsConfig/Page/config.ts` which tells TYPO3 to include and read every file in the created directories (in the future this file will also include others files as well and it is your 'startpoint' to your page TsConfig).

The following configuration should inform TYPO3 to include all files in the created BackendLayouts directory:

    <INCLUDE_TYPOSCRIPT: source="DIR:EXT:site_template/Configuration/BackendLayouts" extensions="ts">

Or if just wanting to include one file: 

    <INCLUDE_TYPOSCRIPT: source="FILE:EXT:site_template/Configuration/BackendLayouts/mytemplate.ts">
    
Tell TYPO3 to automatically load the  created `Configuration/TsConfig/Page/config.ts` (which implicit loads the 'BackendLayouts' again) by adding the following into `ext_tables.php`.

    // Add page TSConfig
    $pageTsConfig = \TYPO3\CMS\Core\Utility\GeneralUtility::getUrl(
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TsConfig/Page/config.ts');
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig($pageTsConfig);
    
The configuration will now been read by TYPO3, so it's time to add the BE-layout of the template. The BE-layout you are going to create is a very simple homepage template (your web_layout) containing three rows with different columns. The template file `homepage.ts` configuration will look like the following:

    mod {
        web_layout {
            BackendLayouts {
                #The template identifier 'homepage'
                myhomepage {
                    # The title visible in the BE
                    title = My Homepage
                    # additional image for the BE
                    icon = EXT:site_template/Resources/Public/Backend/BackendLayoutIcons/homepage.jpg
                    config {
                        backend_layout {
                            # max number of columns
                            colCount = 3
                            # max number of rows
                            rowCount = 2
                            rows {
                                1 {
                                    columns {
                                        1 {
                                            name = Top row 100% width
                                            # An unique identifier for this specific column
                                            colPos = 3
                                            # A additional colspan like normal table colspans
                                            colspan = 3
                                        }
                                    }
                                }
    
                                2 {
                                    columns {
                                        1 {
                                            name = Second row 66% width
                                            colspan = 2
                                            colPos = 0
                                        }
    
                                        2 {
                                            name = Second row right 33% width
                                            colPos = 2
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
     
*The third row you should be able to create yourself (tip: update the max rows number)*

Using this BE-layout (don't forget clearing the cache) can be done by editing a page and adjusting the field `Backend Layout (this page only)` to your homepage (The mentioned title in the configuration). If you want to perform this recursively for the subpages also set the field `Backend Layout (subpages of this page)`).
 
When going to the page module you see the created rows and columns you configured and you can place in here the desired content. Next step is to override the bootstrap_package template definition to automatically load your 'MyHomepage' html template. *The bootstrap_package currently only accepts the templates it defined in the setup.txt, and it is desired to load this dynamically*.
Therefore you need this configuration in your `setup.txt` (OR create a file 'parser.ts' and load this file in your setup.txt as defined before). 

    # This shows optional development errors
    config {
        contentObjectExceptionHandler = 0
    }    
    
    ## Override the template parser of bootstrap_package
    page.10.templateName >
    page.10.templateName = TEXT
    page.10.templateName {
        data = levelfield:-2,backend_layout_next_level,slide
        override.field = backend_layout
        split {
            token = pagets__
            1.current = 1
            1.wrap = |
        }
        case = uppercamelcase
        if.empty.value = Default
    }

You can now visit the website but this will result in a 'Template not found exception'. The template file isn't created yet and you are going to create now with your own HTML markup. *TIP In the exception TYPO3 already mentions in which directories he searched for the expected file*. If it is correct he searches for the file `web/typo3conf/ext/site_template/Resources/Private/Templates/Myhomepage.html`.
So create this file in the correct directory and place some html into this and revisit the website. 

The page is empty, right? This comes because no layout is specified. If you wrap the content of your page to the default layout of the bootstrap package is used. The section 'Main' is used in the layout 'Default' to render that part in the layout.

    <f:layout name="Default"/>
    <f:section name="Main">
        <h1>your html</h1>
    </f:section>

*TIP Look in the bootstrap package how the layout or templates look like, you find here good examples how a html will look like* 

Were is my content? In your html you need to render the content at the places you want. The rendering of one colPos (The unique number you mentioned in the BE-layout configuration) can be done by using a TYPO3 fluid tag.

    <f:section name="Main">
       <f:cObject typoscriptObjectPath="lib.dynamicContent" data="{pageUid: '{data.uid}', colPos: '3'}"/>
    </f:section>

The full example of the MyHomepage template with bootstrap rows can be found at [Myhomepage.html] (/Files/Myhomepage.html)

*(When not using any on the bootstrap_package be-layouts you can disable them in the extensionmanager -> bootstrap_package settings -> 'Disable BackendLayouts')*


Add news extension to your website
==================================

One commonly used functionality of a website is presenting some news/blog/information presented in a list with a detail view.
In TYPO3 the extension 'news' offers the functionality to present this. In this section it will be explained how to add
this to your website, and how to adjust the view of this.

Installation of news can be done like the introduction package, in short:

* Run composer require `composer require typo3-ter/news`
* Activate the extension in the extensionmanager module

When the extension is installed a plugin can be added to a certain page to show all news records of a storage.
The storage can be the page were the plugin is listed, but it is *recommended* to use a folder/storage to keep your website
a structured. The setup of news could be done for example by:

* Create a new folder/storage in the pagetree
* Edit the folder and look for the field 'use as a container' and select 'news'.
* Create a few news items in this folder (List module, add item -> add news)
* Create two pages in the pagetree  for example 'news' and 'news-detail'
 
**The detail-view can be hide in the menus because user are redirect from the list-view and accessing this page will result in an error because the page does not know what to show. *(You can hide the detail view in the edit page mode.)***

* Create a plugin on the created news page in the pageview where you want to show the news.
   * Select page module
   * Select the desired page, in this case 'news'
   * Add content on the desired place 'Add content'
   * Find and select 'news system' and go the plugin settings
* Configure the plugin as followed:
   * Configure what to display: *select 'listview (without overloading detail view)*
   * Configure where your news items are stored: *Enter/search for your folder/container in the field 'select startingpoint'*
   * Configure where to redirect to for an detail page of the news items: *Enter/search for your detail page at the field 'detail pageId'*
   * Save your changes

Presenting the detail of a news items is done by adding another news plugin to your detail page and configure the
starting point to the same folder as previous and selecting 'detail-page' in the field 'what to display'.

When visiting the website (cleared the cache / set the page on visible?) you should see your news items.

The complete user manual as other information about this extension can be found at:
http://typo3.org/extensions/repository/view/news

Adjusting the view(s) of the news extension
-------------------------------------------

After you installed the news extension you probably also want to adjust the view of the news list/ detail-page to your
needs. To do this a few steps are needed and you can adjust the templates of news in the site_template.

Adjusting the view is own extension is mentioning TYPO3 to first look in your extension to the news templates. If the template is found here this template is used otherwise the template of news is found. (are none of the templates are found you get an exception).

At this point you should already have added the typoscript to your website in the part were you adjusted the logo, if you skipped this have a look in that section.

The TypoScript to inform TYPO3 to look for our own templates add the following to the `setup.txt`

	plugin.tx_news {
		view {
			templateRootPaths {
				102 = EXT:site_template/Resources/Private/Ext/News/Templates/
			}

			partialRootPaths {
				102 = EXT:site_template/Resources/Private/Ext/News/Partials/
			}

			layoutRootPaths {
				102 = EXT:site_template/Resources/Private/Ext/News/Layouts/
			}
		}
	}

*Better practise is to created an folder 'Lib' in the TypoScript folder and create a file for every extension you change the settings for (just to be organized). Move the settings of news to for example the file `Configuration/TypoScript/Lib/news.ts` and include all files in the Lib directory in your setup:*

    # Lib
    <INCLUDE_TYPOSCRIPT: source="DIR:EXT:site_template/Configuration/TypoScript/Lib/">

Adjusting the template is now a matter of creating the same file (or copying the original) in the mentioned paths above.

*When no changes are visible, make sure that the path AND filename relative to the given rootPaths are the same, else the template of news is taken. (you can make a small adjustment in the news template to check if the news template is still used)*

Create your first custom extension
----------------------------------

**The case of the first extension is a simple blog system to create, update, edit, delete blogitem(s) on the website as
well in the backend.
On the website there should be an overview of al blog items as well an detail view of every item.
A blog item consists of a title, teaser, date and a message, where the date will be automatically will be set when creating
the blog item.**

Creating extensions can easily be kick started by the extension builder. The command to install the extension builder is `composer require typo3-ter/extensionbuilder` .The manual of the extension builder can be found at http://docs.typo3.org/typo3cms/extensions/extension_builder/. 
*(Do not forget to activate the extension in the extensionmanager)*

Once you have installed the extensionbuilder we can use the backend module to create our simple_blog extension.

The entered information in the demo is as follows:

- Name: Simple blog
- Vendor name: Yourcompany
- Key: simple_blog
- Descr: My first extension, a nice blogging system
- More options: keep defaults
- Add a person: enter your personal information
- Add a front end plugin:
- Name: Listblog
- Key: list

After this create a 'New Model Object', in this case the Blog object.

- Mark the "Is aggregate root" *This creates a repository and creates the mapping*
- Check the required action that should be possible
- At the properties we want the following:

- title (String)
- date (DateTime(timestamp))
- teaser (String)
- message (Rich text)

Save your extension and install this in the extension manager.

Because we use a composer based installation we need to add a composer.json file to autoload the classes if you want to require this extension.

    {
      "name": "beechit/simple-blog",
      "type": "typo3-cms-extension",
      "description": "\"Blogname\": blogging ",
      "license": ["GPL-2.0+"],
      "require": {
        "typo3/cms-core": ">=7.5.0,<8.0"
      },
      "autoload": {
        "psr-4": {
          "BeechIt\\SimpleBlog\\": "Classes"
        }
      }
    }
    
**Because this extension is not yet under version control you can not require the extension yet, therefore add the autoloading also the your main composer file as followed:**

    "autoload": {
        "psr-4": {
            "BeechIt\\SimpleBlog\\": "web/typo3conf/ext/simple_blog/Classes"
        }
    },
    
Then invoke the composer command to regenerate the autoload file `composer dump-autoload`.

If you go to the typo3conf/ext you will see the created extension simple_blog. Under the folder Classes/Domain/Model the
Blog is created with default getters and setters. In here we create a constructor that sets the date on creation of an item.
The following constructor is created which sets the date::

    /**
     * Constructor of a new Blog which automatically sets the date on today
     */
    public function __construct() {
        $this->date = new \DateTime();
    }

The other thing we need to adjust is that a web user does not have to enter the field. In the folder
Resources/private/Partials/Blog/FormFields.html the following has to be removed::

	<label for="date">
	<f:translate key="tx_simpleblog_domain_model_blog.date" />
	</label><br />
	<f:form.textfield property="date"  value="{blog.date->f:format.date()}" /><br />


**The following steps assume that you have already done/read the section about adding the news system, due the fact that some steps are already explained over there.**

If this is all setup you have to create a folder/storage for the blog items and create a page containing your plugin.

As different with the news extension in creating a new content element the plugin is under 'General plugin' and after
this go to the tab 'Plugin' and select your plugin. ('ListBlog' or the name you specified before in the extension builder)

At last enter/find the record of your folder/container and set this in the field 'Record Storage Page'.

When you cleared the caches and visit your website you can see your first blog system. In the backend you can also adjust
your blog items in your container or add new ones if desired.

Links and sources
=================

The final code created in this workshop can be find in the directory [Finalcode] (Finalcode/).

**Official TYPO3 documentation:**

The official documentation of TYPO3 can be found here:

https://docs.typo3.org/

**TYPO3 extbase book**

http://www.amazon.com/TYPO3-Extbase-Modern-Extension-Development/dp/1530534178/ref=sr_1_2?s=books&ie=UTF8&qid=1460974356&sr=1-2

**TYPO3 video instructions:**

https://jweiland.net/video-anleitungen/typo3.html (DE)

**Usefull blogs**

https://usetypo3.com/ (EN)
http://typo3blogger.de/links-der-woche/#more-10491  (DE) 
