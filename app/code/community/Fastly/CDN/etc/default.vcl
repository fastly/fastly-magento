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

    return(lookup);
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

    if (beresp.http.Content-Type ~ "text/(html|xml)") {
        # enable ESI feature for Magento response by default
        esi;
        if (!beresp.http.Vary ~ "Fastly-Cdn-Env,Https") {
            if (beresp.http.Vary) {
                    set beresp.http.Vary = beresp.http.Vary ",Fastly-Cdn-Env,Https";
                } else {
                    set beresp.http.Vary = "Fastly-Cdn-Env,Https";
                }
        }
        # Since varnish doesn't compress ESIs we need to hint to the HTTP/2 terminators to
        # compress it
        set beresp.http.x-compress-hint = "on";
    } else {
        # enable gzip for all static content
        if (http_status_matches(beresp.status, "200,404") && (beresp.http.content-type ~ "^(application\/x\-javascript|text\/css|application\/javascript|text\/javascript|application\/json|application\/vnd\.ms\-fontobject|application\/x\-font\-opentype|application\/x\-font\-truetype|application\/x\-font\-ttf|application\/xml|font\/eot|font\/opentype|font\/otf|image\/svg\+xml|image\/vnd\.microsoft\.icon|text\/plain)\s*($|;)" || req.url.ext ~ "(?i)(css|js|html|eot|ico|otf|ttf|json)" ) ) {
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
        return (deliver);    set req.hash += req.url;

    }

    if (http_status_matches(beresp.status, "200,301,404") && !req.http.X-Pass) {
        if (beresp.http.Content-Type ~ "text/(html|xml)") {
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

    }

    return (deliver);
}

sub vcl_deliver {

    # Add an easy way to see whether custom Fastly VCL has been uploaded
    if ( req.http.Fastly-Debug ) {
        set resp.http.Fastly-Magento-VCL-Uploaded = "1.0.5";
    } else {
        remove resp.http.Fastly-Module-Enabled;
    }

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
    }

    # Clean up Vary before handing off to the user
    if ( !req.http.Fastly-FF ) {
        set resp.http.Vary = regsub(resp.http.Vary, "Fastly-Cdn-Env,Https", "Cookie");
    }

    if (resp.http.magentomarker) {
        # Remove the magic marker
        unset resp.http.magentomarker;

        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, post-check=0, pre-check=0";
        set resp.http.Pragma        = "no-cache";
        set resp.http.Expires       = "Mon, 31 Mar 2008 10:00:00 GMT";
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

sub vcl_pass {
    # Deactivate gzip on origin
    unset bereq.http.Accept-Encoding;

#FASTLY pass
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

sub vcl_hash {
    set req.hash += req.http.host;
    set req.hash += req.url;

    if (!(req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$")) {
        call design_exception;
    }

# Please do not remove below. It's required for purge all functionality
#FASTLY hash

    return (hash);
}


sub design_exception {
}
