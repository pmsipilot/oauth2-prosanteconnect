# ANS / Pro Sante Connect Provider for OAuth 2.0 Client

This package provides ANS / Pro Sante Connect OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Requirements

The following versions of PHP are tested:

* PHP 8.1

Some other versions may work, but are not tested at this time.

To use this package, it will be necessary to have an ANS Pro Sante Connect client ID and client
secret. These are referred to as `{psc-client-id}` and `{psc-client-secret}`
in the documentation.

## Installation

To install, use composer:

```sh
composer require groupepsig/oauth2-prosanteconnect
```

## Usage w/Symfony 6.x

To use this with Symfony 6.x you have to add a client class in your application, something around the lines of:

```php
namespace App\OAuth2\Client\Provider;

use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use League\OAuth2\Client\Token\AccessToken;
use GroupePSIH\OAuth2\Client\Provider\ProSanteConnectResourceOwner;

class ProSanteConnectClient extends OAuth2Client
{
    public function fetchUserFromToken(AccessToken $accessToken): ProSanteConnectResourceOwner|\League\OAuth2\Client\Provider\ResourceOwnerInterface
    {
        return parent::fetchUserFromToken($accessToken);
    }

    public function fetchUser(): ProSanteConnectResourceOwner|\League\OAuth2\Client\Provider\ResourceOwnerInterface
    {
        return parent::fetchUser();
    }
}
```

Use the `knpuniversity/oauth2-client-bundle` 
And make the appropriate modifications in the `config/packages/knpu_oauth2_client.yaml`:

```yaml
knpu_oauth2_client:
    http_client_options:
        proxy: false
        timeout: 5
    clients:
        prosc:
            type: generic
            provider_class: App\OAuth2\Client\Provider\ProSanteConnect
            client_id: '%env(PROSC_CLIENTID)%'
            client_secret: '%env(PROSC_SECRET)%'
            redirect_route: 'prosc_oauth_check'
            redirect_params: {}
            provider_options: {'dev': true}
```

 and `security.yaml`:

```yaml
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            lazy: true
            form_login:
                login_path: prosc_oauth_login
                # for Gitlab (test)
                # login_path: gitlab_oauth_login
            guard:
                authenticators:
                    - App\Security\ProSCAuthenticator
                    # for Gitlab (test)
                    # - App\Security\GitlabAuthenticator
```

`ProSCAuthenticator` being a class extending `SocialAuthenticator` with your business logic.