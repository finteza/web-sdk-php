# Finteza PHP SDK

The official sdk for sending events to Finteza servers and proxying analytical requests via the website.

## Requirement

- PHP 5.3+

## Installation

Use the [Сomposer](http://getcomposer.org/download/) package manager to install SDK easily.

Run the following command from the console:

```
composer require finteza-analytics
```

## Usage

### Sending events

Use the `FintezaAnalytics::event();` method to send events to Finteza

Inputs:

| Parameter | Type | Description |
| --------- | ---- | ----------- |
| name * | string | Event name. The maximum length is 128 symbols. |
| websiteId * | string | Website ID. It can be obtained in the website settings (`ID` field) of the [Finteza panel](https://panel.finteza.com/). |
| url | string | Optional. Finteza server address. |
| referer | string | Optional. Host of a website SDK is called on. |

Example:

```
use FintezaAnalytics;

// sending event
FintezaAnalytics::event( array(
    'name' => 'Server+Track+Test',
    'websiteId' => 'sbnonjcmrvdebluwjzylmbhfkrmiabtqpc'
) );
```

See [Finteza help](https://www.finteza.com/en/developer/php-sdk/php-sdk-events) for more details on sending events.

### Proxying analytical scripts

Use the `FintezaAnalytics::proxy();` method to proxy all analytical requests in Finteza

Inputs:

| Parameter | Type | Description |
| --------- | ---- | ----------- |
| path * | string | Start of the path for requests to be proxied. |
| token * | string | Token for signing the `X-Forwarder-For header`. You can get this value in the website settings (`ID` field) of the [Finteza panel](https://panel.finteza.com/). |
| url | string | Optional. Finteza server address. |

Example:

```
use FintezaAnalytics;

// proxy request 
FintezaAnalytics::proxy( array( 
    "path" => "/fz", 
    "token" => "lopvkgcafvwoprrxlopvkgcafvwfzsrx" 
) );
```

We recommend calling this method at each website request. SDK sorts out analytical requests on its own and proxies them to the Finteza servers.

Also, Finteza counter code installed on the website should be changed for correct operation.

See [Finteza help](https://www.finteza.com/en/developer/insert-code/proxy-script-request) for more details on configuring proxying.

## License

Released under the [BSD License](https://opensource.org/licenses/BSD-3-Clause).