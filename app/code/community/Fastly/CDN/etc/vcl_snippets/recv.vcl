    # Fixup for Varnish ESI not dealing with https:// absolute URLs well
    if (req.is_esi_subreq && req.url ~ "/https://([^/]+)(/.*)$") {
        set req.http.Host = re.group.1;
        set req.url = re.group.2;
    }

    # Pass any checkout, cart or customer/myaccount urls
    if (req.url.path ~ "/(cart|checkout|customer)") {
        set req.http.x-pass = "1";
    # Pass all admin actions
    } else if (req.url.path ~ "^/(index\.php/)?admin(_.*)?/") {
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

    # auth for purging
    if (req.request == "FASTLYPURGE") {

        if (!req.http.X-Purge-Token ~ "^([^_]+)_(.*)" ) {
            error 403;
        }

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
        if (req.http.Cookie ~ "FASTLY_CDN-([A-Za-z0-9-_]+)=([^;]*)") {
            set req.url = querystring.filter(req.url, "esi_data") + "&esi_data=" + re.group.2;
        }
    }

    # Per suggestions in https://github.com/sdinteractive/SomethingDigital_PageCacheParams
    # we'll strip out query parameters used in Google AdWords, Mailchimp tracking
    set req.http.Magento-Original-URL = req.url;
    set req.url = querystring.regfilter(req.url, "^(utm_.*|gclid|gdftrk|_ga|mc_.*)");
    # Sort the query arguments to increase cache hit ratio with query arguments that
    # may be out of order however only on URLs that are not being passed. 
    if ( !req.http.x-pass ) {
        set req.url = boltsort.sort(req.url);
    }
