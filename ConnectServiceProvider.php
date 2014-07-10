<?php

namespace SensioLabs\Connect\Silex;

use Guzzle\Http\Client;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use SensioLabs\Connect\Api\Api;
use SensioLabs\Connect\Api\Parser\VndComSensiolabsConnectXmlParser;
use SensioLabs\Connect\Bridge\Symfony\Form\ErrorTranslator;
use SensioLabs\Connect\OAuthConsumer;
use SensioLabs\Connect\Security\Authentication\ConnectAuthenticationFailureHandler;
use SensioLabs\Connect\Security\Authentication\Provider\ConnectAuthenticationProvider;
use SensioLabs\Connect\Security\EntryPoint\ConnectEntryPoint;

class ConnectServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['sensiolabs_connect.oauth_endpoint'] = 'https://connect.sensiolabs.com';
        $app['sensiolabs_connect.api_endpoint'] = 'https://connect.sensiolabs.com/api';

        $app['sensiolabs_connect.oauth_consumer'] = $app->share(function () use ($app) {
            return new OAuthConsumer(
                $app['sensiolabs_connect.app_id'],
                $app['sensiolabs_connect.app_secret'],
                $app['sensiolabs_connect.app_scope'],
                $app['sensiolabs_connect.oauth_endpoint'],
                $app['sensiolabs_connect.guzzle'],
                $app['logger']
            );
        });

        $app['sensiolabs_connect.api_parser'] = $app->share(function () use ($app) {
            return new VndComSensiolabsConnectXmlParser();
        });

        $app['sensiolabs_connect.api'] = $app->share(function () use ($app) {
            return new Api(
                $app['sensiolabs_connect.api_endpoint'],
                $app['sensiolabs_connect.guzzle'],
                $app['sensiolabs_connect.api_parser'],
                $app['logger']
            );
        });

        $app['sensiolabs_connect.api_root'] = $app->protect(function () use ($app) {
            return $app['sensiolabs_connect.api']->getRoot();
        });

        $app['sensiolabs_connect.error_translator'] = $app->share(function () {
            return new ErrorTranslator();
        });

        $app['security.authentication_listener.factory.sensiolabs_connect'] = $app->protect(function ($name, $options) use ($app) {
            $app['sensiolabs_connect.oauth_callback.'.$name] = str_replace('/', '_', ltrim(isset($options['check_path']) ? $options['check_path'] : '/login_check', '/'));

            $app->match('/sensiolabs_connect/oauth_login_'.$name, function (Request $request) use ($app, $name) {
                return $app['security.entry_point.sensiolabs_connect.'.$name]->start($request);
            })->bind('sensiolabs_connect.oauth_login.'.$name);

            if (!isset($app['security.authentication_provider.'.$name.'.sensiolabs_connect'])) {
                $app['security.authentication_provider.'.$name.'.sensiolabs_connect'] = $app->share(function () use ($app, $name) {
                    return new ConnectAuthenticationProvider($app['security.user_provider.default'], $name);
                });
            }

            if (!isset($app['security.authentication.failure_handler.'.$name])) {
                $app['security.authentication.failure_handler.'.$name] = $app->share(function () use ($app) {
                    return new ConnectAuthenticationFailureHandler($app['security'], $app['logger']);
                });
            }

            if (!isset($app['security.entry_point.sensiolabs_connect.'.$name])) {
                $app['security.entry_point.sensiolabs_connect.'.$name] = $app->share(function () use ($app, $name) {
                    return new ConnectEntryPoint($app['sensiolabs_connect.oauth_consumer'], $app['security.http_utils'], $app['sensiolabs_connect.oauth_callback.'.$name]);
                });
            }

            if (!isset($app['security.authentication_listener.'.$name.'.sensiolabs_connect'])) {
                $options = array_replace(array(
                    'listener_class' => 'SensioLabs\\Connect\\Security\\Firewall\\ConnectAuthenticationListener',
                ), $options);

                $app['security.authentication_listener.'.$name.'.sensiolabs_connect'] = $app['security.authentication_listener.form._proto']($name, $options);
                $app['security.authentication_listener.'.$name.'.sensiolabs_connect'] = $app->share($app->extend('security.authentication_listener.'.$name.'.sensiolabs_connect', function ($listener, $app) use ($name) {
                    $listener->setApi($app['sensiolabs_connect.api']);
                    $listener->setOAuthConsumer($app['sensiolabs_connect.oauth_consumer']);
                    $listener->setOAuthCallback($app['sensiolabs_connect.oauth_callback.'.$name]);

                    return $listener;
                }));
            }

            return array(
                'security.authentication_provider.'.$name.'.sensiolabs_connect',
                'security.authentication_listener.'.$name.'.sensiolabs_connect',
                'security.entry_point.sensiolabs_connect.'.$name,
                'form'
            );
        });

        $app['sensiolabs_connect.guzzle'] = $app->share(function () {
            return new Client();
        });
    }

    public function boot(Application $app)
    {
    }
}
