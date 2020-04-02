    # Add an easy way to see whether custom Fastly VCL has been uploaded
    if ( req.http.Fastly-Debug ) {
        set resp.http.Fastly-Magento-VCL-Uploaded = "1.0.31";
    } else {
        remove resp.http.Fastly-Module-Enabled;
        # remove Varnish/proxy header
        remove resp.http.X-Varnish;
        remove resp.http.Via;
        remove resp.http.X-Purge-URL;
        remove resp.http.X-Purge-Host;
        remove resp.http.Fastly-page-cacheable;
    }

    # Clean up Vary before handing off to the user
    if ( !req.http.Fastly-FF ) {
        set resp.http.Vary = regsub(resp.http.Vary, "Fastly-Cdn-Env,Https", "Cookie");
        remove resp.http.surrogate-keys-set;
    }

    if (resp.http.x-esi && !req.http.Fastly-FF) {
        # Remove the ESI marker
        unset resp.http.x-esi;

        # Tell browsers not to cache the content
        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, post-check=0, pre-check=0";
    }
