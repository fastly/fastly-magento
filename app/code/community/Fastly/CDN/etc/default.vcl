###############################################################################
#
# Fastly CDN for Magento
#
# NOTICE OF LICENSE
#
# This source file is subject to the Fastly CDN for Magento End User License
# Agreement that is bundled with this package in the file LICENSE_FASTLY_CDN.txt.
#
# @copyright   Copyright (c) 2015 Fastly, Inc. (http://www.fastly.com)
# @license     BSD, see LICENSE_FASTLY_CDN.txt
#
###############################################################################

# This is a basic VCL configuration file for fastly CDN for Magento module.

sub vcl_recv {
#FASTLY recv
    # we only deal with GET and HEAD by default
    if (req.request != "GET" && req.request != "HEAD"  && req.request != "FASTLYPURGE") {
        return (pass);
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

    # set HTTPS header for offloaded TLS
    if (req.http.Fastly-SSL) {
        set req.http.Https = "on";
    }

    # disable ESI processing on Origin Shield
    if (req.http.Fastly-FF) {
        set req.esi = false;
    }

    # static files are always cacheable. remove SSL flag and cookie
    if (req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$") {
        unset req.http.Https;
        unset req.http.Cookie;
    }

    # formkey lookup
    if (req.url ~ "/fastlycdn/getformkey/") {
        # check if we have a formkey cookie
        if (req.http.Cookie ~ "FASTLY_CDN_FORMKEY") {
            set req.http.Formkey = regsub(req.http.cookie, ".*FASTLY_CDN_FORMKEY=([^;]*)(;*.*)?", "\1");
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
        if (req.http.Cookie ~ "FASTLY_CDN_GEOIP_PROCESSED") {
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

             return (lookup);
         }
     }

    return(lookup);
}

sub vcl_pass {
#FASTLY pass
}

sub vcl_hash {
    set req.hash += req.http.Https;
    set req.hash += req.http.host;
    set req.hash += req.url;

    if (req.http.cookie ~ "FASTLY_CDN_ENV=") {
        set req.http.fastlyCDNEnv = regsub(
            req.http.cookie,
            "(.*)FASTLY_CDN_ENV=([^;]*)(.*)",
            "\2"
        );
        set req.hash += req.http.fastlyCDNEnv;
        unset req.http.fastlyCDNEnv;
    }

    set req.hash += "#####GENERATION#####";

    if (!(req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$")) {
        call design_exception;
    }
    return (hash);
}

sub vcl_hit {
#FASTLY hit

    if (!obj.cacheable) {
        return(pass);
    }
    return(deliver);
}

sub vcl_miss {
    # Deactivate gzip on origin
    unset bereq.http.Accept-Encoding;

#FASTLY miss

    return(fetch);
}

sub vcl_fetch {
#FASTLY fetch

    if (beresp.status >= 500) {
        # let SOAP errors pass - better debugging
        if (beresp.http.Content-Type ~ "text/xml") {
            return (deliver);
        }

        if (req.restarts < 1 && (req.request == "GET" || req.request == "HEAD")) {
            restart;
        }
    }

    if (req.restarts > 0 ) {
        set beresp.http.Fastly-Restarts = req.restarts;
    }

    if (beresp.http.Content-Type ~ "text/html" || beresp.http.Content-Type ~ "text/xml") {
        # enable ESI feature for Magento response by default
        esi;
    } else {
        # enable gzip for all static content
        if ((beresp.status == 200 || beresp.status == 404) && (beresp.http.content-type ~ "^(application\/x\-javascript|text\/css|application\/javascript|text\/javascript|application\/json|application\/vnd\.ms\-fontobject|application\/x\-font\-opentype|application\/x\-font\-truetype|application\/x\-font\-ttf|application\/xml|font\/eot|font\/opentype|font\/otf|image\/svg\+xml|image\/vnd\.microsoft\.icon|text\/plain)\s*($|;)" || req.url ~ "\.(css|js|html|eot|ico|otf|ttf|json)($|\?)" ) ) {
            # always set vary to make sure uncompressed versions dont always win
            if (!beresp.http.Vary ~ "Accept-Encoding") {
                if (beresp.http.Vary) {
                    set beresp.http.Vary = beresp.http.Vary ", Accept-Encoding";
                } else {
                    set beresp.http.Vary = "Accept-Encoding";
                }
            }
            if (req.http.Accept-Encoding == "gzip") {
                set beresp.gzip = true;
            }
        }
    }

    if (beresp.http.Cache-Control ~ "private") {
        set req.http.Fastly-Cachetype = "PRIVATE";
        return (pass);
    }

    if (beresp.status == 500 || beresp.status == 503) {
        set req.http.Fastly-Cachetype = "ERROR";
        set beresp.ttl = 1s;
        set beresp.grace = 5s;
        return (deliver);
    }

    if (beresp.status == 200 || beresp.status == 301 || beresp.status == 404) {
        if (beresp.http.Content-Type ~ "text/html" || beresp.http.Content-Type ~ "text/xml") {
            # marker for vcl_deliver to reset Age:
            set beresp.http.magentomarker = "1";

            # Don't cache cookies
            unset beresp.http.set-cookie;
        } else {
            if (beresp.http.Expires || beresp.http.Surrogate-Control ~ "max-age" || beresp.http.Cache-Control ~ "(s-maxage|max-age)") {
                # keep the ttl here
            } else {
                # apply the default ttl
                set beresp.ttl = 3600s;
            }
        }

        # init surrogate keys
        if (beresp.http.Surrogate-Key) {
            set beresp.http.Surrogate-Key = beresp.http.Surrogate-Key " text";
        } else {
            set beresp.http.Surrogate-Key = "text";
        }

        # set surrogate keys by content type
        if (beresp.http.Content-Type ~ "image") {
            set beresp.http.Surrogate-Key = "image";
        } elsif (beresp.http.Content-Type ~ "script") {
            set beresp.http.Surrogate-Key = "script";
        } elsif (beresp.http.Content-Type ~ "css") {
            set beresp.http.Surrogate-Key = "css";
        }

        set beresp.http.X-Surrogate-Key = beresp.http.Surrogate-Key;
    }

    return (deliver);
}

sub vcl_deliver {
    # debug info
    if (resp.http.X-Cache-Debug) {
        if (obj.hits > 0) {
            set resp.http.X-Cache      = "HIT";
            set resp.http.X-Cache-Hits = obj.hits;
        } else {
            set resp.http.X-Cache      = "MISS";
        }
        set resp.http.X-Cache-Expires  = resp.http.Expires;
    } else {
        # remove Varnish/proxy header
        remove resp.http.X-Varnish;
        remove resp.http.Via;
        remove resp.http.Age;
        remove resp.http.X-Purge-URL;
        remove resp.http.X-Purge-Host;
        remove resp.http.X-Surrogate-Key;
    }

    if (resp.http.magentomarker) {
        # Remove the magic marker
        unset resp.http.magentomarker;

        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, post-check=0, pre-check=0";
        set resp.http.Pragma        = "no-cache";
        set resp.http.Expires       = "Mon, 31 Mar 2008 10:00:00 GMT";
        set resp.http.Age           = "0";
    }

#FASTLY deliver

    return (deliver);
}

sub vcl_error {
    # workaround for possible security issue
    if (req.url ~ "^\s") {
        set obj.status = 400;
        set obj.response = "Malformed request";
        synthetic "";
        return (deliver);
    }

    # geo ip request
    if (obj.status == 750) {
        set req.url = regsub(req.url, "(/fastlycdn/esi/getcountry/.*)", "/fastlycdn/esi/getcountryaction/?country_code=") obj.response;
        return (restart);
    }

    # geo ip country code
    if (obj.status == 755) {
        set obj.status = 200;
	    synthetic obj.response;
        return(deliver);
    }

    # formkey request
    if (obj.status == 760) {
        set obj.status = 200;
	    synthetic obj.response;
        return (deliver);
    }

    # error 200
    if (obj.status == 200) {
        return (deliver);
    }

     set obj.http.Content-Type = "text/html; charset=utf-8";
     set obj.http.Retry-After = "5";
     synthetic {"
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
    <head>
        <title>"} obj.status " " obj.response {"</title>
    </head>
    <body>
        <h1>Error "} obj.status " " obj.response {"</h1>
        <p>"} obj.response {"</p>
        <h3>Guru Meditation:</h3>
        <p>XID: "} req.xid {"</p>
        <hr>
        <p>Fastly CDN server</p>
    </body>
</html>
"};

#FASTLY error
}

sub design_exception {
}
