# Run this script before new release to embed version tag in the right places so
# we can debug easier
VERSION=`cat VERSION`

sed -i "s/resp.http.Fastly-Magento-VCL-Uploaded = \".*\"/resp.http.Fastly-Magento-VCL-Uploaded = \"$VERSION\"/g" ./app/code/community/Fastly/CDN/etc/default.vcl
sed -i "s/\"version\": \".*\"/\"version\": \"$VERSION\"/g" composer.json
sed -i "s/\"Fastly-Module-Enabled\", \".*\"/\"Fastly-Module-Enabled\", \"$VERSION\"/g" app/code/community/Fastly/CDN/Helper/Cache.php
