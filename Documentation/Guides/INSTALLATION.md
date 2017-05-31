# Installation guide

This guide will show you how to install and configure Fastly CDN extension for Magento 1.

You may choose between two installation methods – composer installation and manual installation by downloading the zip file.

We will go with a second approach - manually downloading the zip file. 

1. Download the zip archive of this repository to a temporary location on your server and extract it.
2. Copy the contents of the app directory in the archive to the app directory of your Magento instance.
3. Go to the Magento backend and open the Cache Management (System -> Cache Management) and refresh the configuration and layout cache.
3. Due to Magento's permission system, please log out and log in again before continuing with the next step.

If you want to install via composer, you can read step by step instructions [here](../README.md#2-installation).

That’s it, you have successfully installed the Fastly CDN extension. Let’s move to configuration.

###Configuration

In order to use the Fastly Cdn extension, you will have to [register a free Fastly account](https://www.fastly.com/signup).

Once you register and verify your account, login to Fastly:

![Fastly Wizard](../images/guides/installation/wizard.png "Fastly Wizard")

You will see a welcome wizard with two input fields which you should fill with:

* Your website domain for Fastly to use when routing requests
* The hostname (or IP address) and port number for your origin server

On the next screen, Fastly is offering you to enable gzip, logging and health check of your origin – you can enable this later. Click continue.

![New Features](../images/guides/installation/newfeatures.png "New Features")

On the final screen, you will have to **point your CNAME to Fastly.** Doing this, you will direct traffic from the Internet through Fastly instead of immediately through your store. You can read more here on how to achieve this.

![Go Live](../images/guides/installation/golive.png "Go Live")

Once you’ve finished with pointing your CNAME to Fastly, let’s configure Magento.

Login to you Magento admin and go to:
```
System > Configuration > Services > Fastly CDN
```

Under the **General Settings** tab, set **Enable cache module** to **Yes** and then enter your **Fastly Service ID*** and **Fastly API key****. 

![Choose Fastly](../images/guides/installation/choosefastly.png "Choose Fastly")

**To find out you Service ID, login to the Fastly dashboard, locate your **Service name** and click on the **Show Service ID** link.*

![Show Service Id](../images/guides/installation/show-service-id.png "Show Service Id")

***To find out your API key, while in the Fastly dashboard, select **Account** from the user menu and scroll way down to the bottom of the page. In the **Account API Key** area, click the **Show** button.*

![API key](../images/guides/installation/account-api-key.png "API key")

You can press the **Test credentials** button just to make sure that you have entered valid credentials.

![Test Credentials](../images/guides/installation/testcredentials.png "Test Credentials")

If you have received a success message, press the **Save Config** button.

![Save config](../images/guides/installation/saveconfig.png "Clear cache")

The final step is to upload the **VCL to Fastly.** You can do this by pressing the **Upload VCL to Fastly** button.

The modal window will pop up, make sure that the **Activate VCL after upload** is ticked and press the **Upload** button in the top right corner:

![VCL upload](../images/guides/installation/vclupload.png "VCL upload")

Once the upload process is done, the modal window will automatically close and the success message will show:

![Success VCL](../images/guides/installation/successvcl.png "Success VCL")

That’s it, you have successfully configured your Magento store with the Fastly CDN.

### General settings

You can read more about general settings [here](../README.md#31-general-settings).

### Purging

You can purge Fastly CDN content through the Magento admin by going to System > Cache Management. You can purge content by the following options:

* Purge by content type
* Purge by store
* Purge a URL
* Purge all

You can read more about purging [here](../README.md#4-cache-cleaning-purge-requests).

