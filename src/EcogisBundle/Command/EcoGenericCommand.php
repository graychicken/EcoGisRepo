<?php

namespace EcogisBundle\Command;

use Symfony\Component\Console\Command\Command;
use ezcDbFactory;
use ezcDbInstance;

abstract class EcoGenericCommand extends Command
{
    static $dsn;
    static $authOptions;

    protected function dbConnect()
    {
        $dsn = \ConfigClass::getDsn();
        $txtDsn = "{$dsn['dbtype']}://{$dsn['dbuser']}:{$dsn['dbpass']}@{$dsn['dbhost']}/{$dsn['dbname']}";
        try {
            $db = ezcDbFactory::create($txtDsn);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            if (isset($dsn['charset'])) {
                $db->exec("SET client_encoding TO '{$dsn['charset']}'");
            }
            if (isset($dsn['search_path'])) {
                $db->exec("SET search_path TO {$dsn['search_path']}, public");
            }
            $db->exec("SET datestyle TO ISO");
            ezcDbInstance::set($db);
        } catch (\PDOException $e) {
            throw new \Exception("Error connecting to database {$dsn['dbname']} on  {$dsn['dbhost']} as  {$dsn['dbuser']}: {$e->getMessage()}");
        }
    }

    protected function authLogin($user, $domain)
    {
        $authOptions = \ConfigClass::getAuthOptions();
        $db = ezcDbInstance::get();
        $auth = new \R3AuthManager($db, $authOptions, APPLICATION_CODE);
        $somain = strtoupper($domain);
        $isAuth = $auth->performTrustLoginAsUser($user, $domain);
        if (!$isAuth) {
            throw new \Exception("Trust authentication error for user {$user}@{$domain} ");
        }

        $auth = \R3AuthInstance::set($auth);
    }

    protected function setLangOptions()
    {
        $langSettings = \ConfigClass::getLanguageSettings();
        \R3Locale::setLanguages($langSettings['languages']);
        \R3Locale::setJqueryDateFormat($langSettings['jQueryDateFormat']);
        \R3Locale::setPhpDateFormat($langSettings['phpDateFormat']);
        \R3Locale::setPhpDateTimeFormat($langSettings['phpDateTimeFormat']);
    }
}