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
        # extract token signature and expiration
        set req.http.X-Sig = regsub(req.http.X-Purge-Token, "^[^_]+_(.*)", "\1");
        set req.http.X-Exp = regsub(req.http.X-Purge-Token, "^([^_]+)_.*", "\1");

        # validate signature
        if (req.http.X-Sig == regsub(digest.hmac_sha1(req.service_id, req.url.path req.http.X-Exp), "^0x", "")) {

            # use vcl time math to check expiration timestamp
            set req.http.X-Original-Grace = req.grace;
            set req.grace = std.atoi(strftime({"%s"}, now));
            set req.grace -= std.atoi(req.http.X-Exp);

            if (std.atoi(req.grace) > 0) {
                error 410;
            }

            # clean up grace since we used it for time math
            set req.grace = std.atoi(req.http.X-Original-Grace);
            unset req.http.X-Original-Grace;

        } else {
            error 403;
        }

        # cleanup variables
        unset req.http.X-Purge-Token;
        unset req.http.X-Sig;
        unset req.http.X-Exp;
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
         if (req.http.Cookie ~ "FASTLY_CDN-") {
             # get the cookie name to look for
             set req.http.fastlyCDNEsiCookieName = regsub(
                 req.url,
                 "(.*)esi_data=([^&]*)(.*)",
                 "\2"
             );

             # get the cookie value for the cookie name
             # tmp string is NAMESPACE-COOKIENAME@@@@@COMPLETE_COOKIE
             # left of @@@@@ is back-referenced in regex to extract value from cookie
             set req.http.fastlyTmp = "FASTLY_CDN-" req.http.fastlyCDNEsiCookieName "@@@@@" req.http.Cookie;
             set req.http.fastlyCDNRequest = regsub(
                 req.http.fastlyTmp,
                 "^([A-Za-z0-9-_]+)@@@@@.*\1=([^;]*)",
                 "\2"
             );

             # do we have a value for the cookie name?
             if (!req.http.fastlyCDNRequest) {
                 set req.http.fastlyCDNRequest = "default";
             }

             # build backend url
             set req.url = regsub(
                   req.url,
                   "(.*)esi_data=([^&]*)[&]?(.*)",
                   "\1\3"
                 )
                 "&esi_data="
                 req.http.fastlyCDNRequest;

             # clean up temp variables
             remove req.http.fastlyCDNEsiCookieName;
             remove req.http.fastlyCDNRequest;

         }
     }

