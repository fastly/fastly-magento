# ESI example implementation

We'll explain how to customize ESI fragments by implementing a sample `deliverycountdown/deliveryCountdown` fragment.

First you will need to insert code below in `config.xml`. It needs to be placed inside parent XML node for `fastlycdn_esi_tags`:

```
<custom_esi_deliverycountdown>
   <block>deliverycountdown/deliveryCountdown</block>
   <esi_tag>Fastly_CDN_Model_Esi_Tag_DeliveryCountDown</esi_tag>
</custom_esi_deliverycountdown>
```

Block node contains type of your block and `esi_tag` custom php class which is in charge of replacing block HTML output with ESI tags.

In custom class (used in `esi_tag` node) `Fastly_CDN_Model_Esi_Tag_DeliveryCountDown` you should declare two constants:

`COOKIE_NAME` and `ESI_URL`

Your custom class should something like this

```
class Fastly_CDN_Model_Esi_Tag_DeliveryCountDown 
extends Fastly_CDN_Model_Esi_Tag_Abstract
{
   const COOKIE_NAME = 'deliverycountdown';
   const ESI_URL     = 'fastlycdn/esi/deliverycountdown';
}
```

After you configure it, ESI directives should be present in HTML source and if ESI debug is on you should see the red
box with ESI URL.

## Possible issues with ESI directives

If you don’t see the ESI directives please investigate following

1. If caching is turned on, flush cache then check parent blocks
2. Check does your custom block fire event `core_block_abstract_to_html_after`. All logic for implementation of
  ESI is in observer `Fastly_CDN_Model_Observer` and in method `replaceBlockByEsiTag`


## Example of properly configured ESI

When ESI is properly configured then page HTML should look like the example below (this is example for cart block):

```
  <esi:remove>
  <!--{CART_SIDEBAR_28b3b89883b2a9844eff129ac712a382}-->
  <div class="flyout-wrapper minicart-wrapper">

    <p class="block-title">
      My Cart<a class="close skip-link-close" href="#" title="Close"><span class="icon-close"></span></a>
    </p>
    <div class="minicart-wrapper-content">
      <div id="minicart-error-message" class="minicart-message"></div>
      <div id="minicart-success-message" class="minicart-message"></div>
        <p class="empty">You have no items in your shopping cart.</p>
    </div>
  </div>
  <!--/{CART_SIDEBAR_28b3b89883b2a9844eff129ac712a382}-->
  </esi:remove>

  <esi:include src='/fastlycdn/esi/checkout_cart_sidebar/?esi_data=checkout_quote&is_secure=1&layout_handles=default&layout_name=minicart_content&private=1' />
```

OPTIONAL: You may possible need to configure Fastly VCL snippets for specific ESI call.
