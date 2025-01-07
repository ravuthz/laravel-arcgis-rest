# Arcgis REST laravel package

## Installation

```bash

composer require ravuthz/arcgis-rest

php artisan vendor:publish --provider="Ravuthz\ArcgisRest\ArcgisRestServiceProvider"

```

## Update .env for Arcgis Rest API

```bash

# Arcgis Rest API Congfiguration
ARCGIS_URL="https://org.arcgis.com/hostedserv/rest/services/<service_name>/FeatureServer"
ARCGIS_PORTAL="https://org.arcgis.com/portal/sharing/rest"
ARCGIS_USERNAME="<arcgis_username>"
ARCGIS_PASSWORD="<arcgis_password>"

```
