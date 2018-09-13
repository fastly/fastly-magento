    if (beresp.status >= 500 && beresp.status < 600) {
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

    if (beresp.http.X-Esi) {
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
        if (http_status_matches(beresp.status, "200,404") && (beresp.http.content-type ~ "^(application\/x\-javascript|text\/css|text\/html|application\/javascript|text\/javascript|application\/json|application\/vnd\.ms\-fontobject|application\/x\-font\-opentype|application\/x\-font\-truetype|application\/x\-font\-ttf|application\/xml|font\/eot|font\/opentype|font\/otf|image\/svg\+xml|image\/vnd\.microsoft\.icon|text\/plain)\s*($|;)" || req.url.ext ~ "(?i)(css|js|html|eot|ico|otf|ttf|json)" ) ) {
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

    # Just in case the Request Setting for x-pass is missing
    if (req.http.x-pass) {
        return (pass);
    }

    # Force any responses with private, no-cache or no-store in Cache-Control to pass
    if (beresp.http.Cache-Control ~ "private|no-cache|no-store") {
        set req.http.Fastly-Cachetype = "PRIVATE";
        return (pass);
    }

    # Varnish sets default TTL if none of these are present. Assume if they are not present that we don't want to cache
    if (!beresp.http.Expires && !beresp.http.Surrogate-Control ~ "max-age" && !beresp.http.Cache-Control ~ "(s-maxage|max-age)") {
        set beresp.ttl = 0s;
        set beresp.cacheable = false;
    }

    # If origin provides TTL for an object we cache it
    if ( beresp.ttl > 0s && (req.request == "GET" || req.request == "HEAD") && !req.http.x-pass ) {
        # Don't cache cookies - this is here because Magento sets cookies even for anonymous users
        # which busts cache
        unset beresp.http.set-cookie;

        # If surrogate keys have been set do not set them again
        if ( !beresp.http.surrogate-keys-set ) {
            if (beresp.http.x-esi) {
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

            set beresp.http.surrogate-keys-set = "1";
        }

    }

    # If for whatever reason we get a 404 on static asset requests make sure we strip out set-cookies. Otherwise we run
    # the risk of resetting the session since we strip out user cookies for static paths
    if (beresp.status == 404 && req.url.path ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico|webp|svg)$") {
        unset beresp.http.set-cookie;
    }
