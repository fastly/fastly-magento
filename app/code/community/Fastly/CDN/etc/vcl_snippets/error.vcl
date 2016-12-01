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