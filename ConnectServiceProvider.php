<?php

namespace SensioLabs\Connect\Silex;

use Guzzle\Plugin\History\HistoryPlugin;
use SensioLabs\Connect\Profiler\ConnectDataCollector;
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
        $app['sensiolabs_connect.options'] = array();
        $app['sensiolabs_connect.oauth_endpoint'] = OAuthConsumer::ENDPOINT;
        $app['sensiolabs_connect.api_endpoint'] = Api::ENDPOINT;

        $app['sensiolabs_connect.oauth_consumer'] = $app->share(function () use ($app) {
            return new OAuthConsumer(
                $app['sensiolabs_connect.app_id'],
                $app['sensiolabs_connect.app_secret'],
                $app['sensiolabs_connect.app_scope'],
                $app['sensiolabs_connect.oauth_endpoint'],
                $app['sensiolabs_connect.guzzle-conf'],
                $app['logger']
            );
        });

        $app['sensiolabs_connect.api_parser'] = $app->share(function () use ($app) {
            return new VndComSensiolabsConnectXmlParser();
        });

        $app['sensiolabs_connect.api'] = $app->share(function () use ($app) {
            return new Api(
                $app['sensiolabs_connect.api_endpoint'],
                $app['sensiolabs_connect.guzzle-conf'],
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

        $app['sensiolabs_connect.guzzle-plugins'] = array();

        $app['sensiolabs_connect.guzzle-conf'] = $app->share(function (Application $app) {
            return array(
                'plugins'         => $app['sensiolabs_connect.guzzle-plugins'],
                'timeout'         => $app['sensiolabs_connect.options-merged']['timeout'],
                'connect_timeout' => $app['sensiolabs_connect.options-merged']['connect_timeout'],
            );
        });

        $app['sensiolabs_connect.options-merged'] = $app->share(function (Application $app) {
            return array_replace(array(
                'connect_timeout' => \SensioLabs\Connect\CONNECT_TIMEOUT,
                'timeout' => \SensioLabs\Connect\TRANSFERT_TIMEOUT,
                'history-limit' => 1000,
            ), $app['sensiolabs_connect.options']);
        });
    }

    public function boot(Application $app)
    {
        if (isset($app['profiler'])) {
            $app['sensiolabs_connect.guzzle-history-plugin'] = $app->share(function (Application $app) {
                $plugin = new HistoryPlugin();
                $plugin->setLimit($app['sensiolabs_connect.options-merged']['history-limit']);// (1000)

                return $plugin;
            });

            $app['sensiolabs_connect.guzzle-plugins'] = array_merge(
                $app['sensiolabs_connect.guzzle-plugins'],
                array($app['sensiolabs_connect.guzzle-history-plugin'])
            );

            $app['data_collectors']= array_merge($app['data_collectors'], array(
                'connect-sdk' => $app->share(function ($app) {
                    return new ConnectDataCollector($app['sensiolabs_connect.guzzle-history-plugin']);
                }),
            ));
            $app['data_collector.templates'] = array_merge($app['data_collector.templates'], array(
                array('connect-sdk', '@ConnectSDK/Collector/connect-sdk.html.twig')
            ));

            $app['sensiolabs_connect.profiler.templates_path'] = $app->share(function () {
                $ref = new \ReflectionClass('SensioLabs\Connect\Profiler\ConnectDataCollector');

                return dirname($ref->getFileName()).'/Resources/views';
            });

            $app['twig.loader.filesystem'] = $app->share($app->extend('twig.loader.filesystem', function ($loader, $app) {
                $loader->addPath($app['sensiolabs_connect.profiler.templates_path'], 'ConnectSDK');

                return $loader;
            }));
        }
    }
}
