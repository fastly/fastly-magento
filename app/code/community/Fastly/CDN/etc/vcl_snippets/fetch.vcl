    if (beresp.status >= 500 && beresp.status < 600) {
        # let SOAP errors pass - better debugging
        if (beresp.http.Content-Type ~ "text/xml") {
            return (deliver);
        }

        /* deliver stale if the object is available */
        if (stale.exists) {
            return(deliver_stale);
        }

        if (req.restarts < 1 && (req.request == "GET" || req.request == "HEAD")) {
            restart;
        }

        /* else go to vcl_error to deliver a synthetic */
        error beresp.status beresp.response;

    }

    if (beresp.http.X-Esi || beresp.http.Content-Type ~ "^text/(html|xml)") {
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

    # Force any responses with private, no-cache or no-store in Cache-Control to pass
    if (beresp.http.Cache-Control ~ "private|no-cache|no-store") {
        set req.http.Fastly-Cachetype = "PRIVATE";
        return (pass);
    }

    # Just in case the Request Setting for x-pass is missing
    if (req.http.x-pass) {
        return (pass);
    }

    # Varnish sets default TTL if none of these are present
    if (!beresp.http.Expires && !beresp.http.Surrogate-Control ~ "max-age" && !beresp.http.Cache-Control ~ "(s-maxage|max-age)") {
        set beresp.ttl = 0s;
    }

    # If origin provides TTL for an object we cache it
    if ( beresp.ttl > 0s && (req.request == "GET" || req.request == "HEAD") && !req.http.x-pass ) {
        if (beresp.http.Content-Type ~ "^text/(html|xml)") {
            # marker for vcl_deliver to reset Age:
            set beresp.http.magentomarker = "1";

            # Don't cache cookies - this is here because Magento sets cookies even for anonymous users
            # which busts cache
            unset beresp.http.set-cookie;

            # init surrogate keys
            if (beresp.http.Surrogate-Key) {
                set beresp.http.Surrogate-Key = beresp.http.Surrogate-Key " text";
            } else {
                set beresp.http.Surrogate-Key = "text";
            }
        } else if (beresp.http.Content-Type ~ "(image|script|css)") {
            # set surrogate keys by content type if they are image/script or CSS
            if (beresp.http.Surrogate-Key) {
                set beresp.http.Surrogate-Key = beresp.http.Surrogate-Key " " re.group.1;
            } else {
                set beresp.http.Surrogate-Key = re.group.1;
            }
        }
    }
