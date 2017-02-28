    # Fixup for Varnish ESI not dealing with https:// absolute URLs well
    if (req.is_esi_subreq && req.url ~ "/https://([^/]+)(/.*)$") {
        set req.http.Host = re.group.1;
        set req.url = re.group.2;
    }

    # Sort the query arguments
    set req.url = boltsort.sort(req.url);

    # bypass language switcher
    if (req.url ~ "(?i)___from_store=.*&___store=.*") {
        set req.http.X-Pass = "1";
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
    if (req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$") {
        unset req.http.Https;
        unset req.http.Cookie;
    }

    # formkey lookup
    if (req.url ~ "/fastlycdn/getformkey/") {
        # check if we have a formkey cookie
        if (req.http.Cookie:FASTLY_CDN_FORMKEY) {
            set req.http.Formkey = req.http.Cookie:FASTLY_CDN_FORMKEY;
        } else {
            # create formkey
            set req.http.seed = req.http.Cookie client.ip remote.port geoip.longitude geoip.latitude geoip.postal_code;
            set req.http.Formkey = regsub(digest.hash_md5(req.http.seed), "^0x", "");
        }
        error 760 req.http.Formkey;
    }

    # geoip lookup
    if (req.url ~ "fastlycdn/esi/getcountry/") {
        # check if GeoIP has been already processed by client
        if (req.http.Cookie:FASTLY_CDN_GEOIP_PROCESSED) {
            error 200 "";
        } else {
            # modify req.url and restart request processing
            error 750 geoip.country_code;
        }
    }

    # geoip get country code
    if (req.url ~ "fastlycdn/esi/getcountrycode/") {
        # create and set req.http.X-Country-Code
        error 755 geoip.country_code;
    }

    # check for ESI calls
    if (req.url ~ "esi_data=") {
        # check for valid cookie data
        if (req.http.Cookie ~ "FASTLY_CDN-([A-Za-z0-9-_]+)=([^;]*)") {
            set req.url = querystring.filter(req.url, "esi_data") + "&esi_data=" + re.group.2;
        }
    }

    # If object has been marked as pass pass it
    if ( req.http.X-Pass ) {
        return(pass);
    }
