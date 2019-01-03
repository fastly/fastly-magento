    # Fixup for Varnish ESI not dealing with https:// absolute URLs well
    if (req.is_esi_subreq && req.url ~ "/https://([^/]+)(/.*)$") {
        set req.http.Host = re.group.1;
        set req.url = re.group.2;
    }

    # Pass any checkout, cart or customer/myaccount urls
    if (req.url.path ~ "/(cart|checkout|customer)") {
        set req.http.x-pass = "1";
    # Pass all admin actions
    } else if (req.url.path ~ "^/(index\.php/)?####ADMIN_PATH####/") {
        set req.http.x-pass = "1";
    # bypass language switcher
    } else if (req.url.qs ~ "(?i)___from_store=.*&___store=.*") {
        set req.http.x-pass = "1";
    }

    # set HTTPS header for offloaded TLS
    if (req.http.Fastly-SSL) {
        set req.http.Https = "on";
    }

    if (req.http.cookie:FASTLY_CDN_ENV) {
        set req.http.Fastly-Cdn-Env = req.http.cookie:FASTLY_CDN_ENV;
    } else {
        unset req.http.Fastly-Cdn-Env;
    }

    ############################################################################################################
    # Following code block controls purge by URL. By default we want to protect all URL purges. In general this
    # is addressed by adding Fastly-Purge-Requires-Auth request header in vcl_recv however this runs the risk of
    # exposing API tokens if user attempts to purge non-https URLs. For this reason inside the Magento module
    # we use X-Purge-Token. Unfortunately this breaks purge from the Fastly UI. Therefore in the next code block
    # we check for presence of X-Purge-Token. If it's not present we force the Fastly-Purge-Requires-Auth
    if (req.request == "FASTLYPURGE") {
        # extract token signature and expiration
        if (req.http.X-Purge-Token && req.http.X-Purge-Token ~ "^([^_]+)_(.*)" ) {

            declare local var.X-Exp STRING;
            declare local var.X-Sig STRING;
            /* extract token expiration and signature */
            set var.X-Exp = re.group.1;
            set var.X-Sig = re.group.2;

            /* validate signature */
            if (var.X-Sig == regsub(digest.hmac_sha1(req.service_id, req.url.path var.X-Exp), "^0x", "")) {
            /* check that expiration time has not elapsed */
                if (time.is_after(now, std.integer2time(std.atoi(var.X-Exp)))) {
                    error 410;
                }
            } else {
                error 403;
            }
        } else {
            set req.http.Fastly-Purge-Requires-Auth = "1";
        }
    }

    # disable ESI processing on Origin Shield
    if (req.http.Fastly-FF) {
        set req.esi = false;
        # Needed for proper handling of stale while revalidated when shielding is involved
        set req.max_stale_while_revalidate = 0s;
    }

    # static files are always cacheable. remove SSL flag and cookie
    if (req.url.path ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico|webp|svg)$") {
        unset req.http.Https;
        unset req.http.Cookie;
    }

    # formkey lookup
    if (req.url.path ~ "/fastlycdn/getformkey/") {
        # check if we have a formkey cookie
        if (req.http.Cookie:FASTLY_CDN_FORMKEY) {
            set req.http.Formkey = req.http.Cookie:FASTLY_CDN_FORMKEY;
        } else {
            # create formkey
            set req.http.seed = req.http.Cookie client.ip remote.port client.geo.longitude client.geo.latitude client.geo.postal_code;
            set req.http.Formkey = regsub(digest.hash_md5(req.http.seed), "^0x", "");
        }
        error 760 req.http.Formkey;
    }

    # client.geo lookup
    if (req.url.path ~ "fastlycdn/esi/getcountry/") {
        # check if GeoIP has been already processed by client
        if (req.http.Cookie:FASTLY_CDN_GEOIP_PROCESSED) {
            error 200 "";
        } else {
            # modify req.url and restart request processing
            error 750 client.geo.country_code;
        }
    }

    # client.geo get country code
    if (req.url.path ~ "fastlycdn/esi/getcountrycode/") {
        # create and set req.http.X-Country-Code
        error 755 client.geo.country_code;
    }

    # check for ESI calls
    if (req.url.qs ~ "esi_data=") {
        # check for valid cookie data
        declare local var.esi_data_field STRING;
        declare local var.cookie_data STRING;
        # Based on esi_data value requested we will need to search for cookie FASTLY_CDN-<type> e.g. FASTLY_CDN-customer_quote
        set var.esi_data_field = "FASTLY_CDN-" subfield(req.url.qs, "esi_data", "&");
        # We can't use variables in either subfield or regex so we need to use this workaround
        # to extract value of cookie that we compiled in esi_data_field
        set var.cookie_data = std.strstr(req.http.Cookie,var.esi_data_field);
        set var.cookie_data = regsub(var.cookie_data,"^[^=]*=([^;]*).*","\1");
        # If found a value we replace the query string with the contents of that cookie
        if ( var.cookie_data != "" ) {
          set req.url = querystring.set(req.url, "esi_data", var.cookie_data);
        }
    }

    # Per suggestions in https://github.com/sdinteractive/SomethingDigital_PageCacheParams
    # we'll strip out query parameters used in Google AdWords, Mailchimp tracking
    set req.http.Magento-Original-URL = req.url;
    set req.url = querystring.regfilter(req.url, "^(####QUERY_PARAMETERS####)");
    # Sort the query arguments to increase cache hit ratio with query arguments that
    # may be out of order however only on URLs that are not being passed. 
    if ( !req.http.x-pass ) {
        set req.url = boltsort.sort(req.url);
    }
