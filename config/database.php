<?php

/*
 * Database connection settings.
 *
 * Local (XAMPP): no extra setup — the defaults below are used.
 * AWS (EC2):     the launch script writes /etc/studyhive/config.php
 *                (outside the web root) with the RDS endpoint and password,
 *                which are read from SSM Parameter Store at boot.
 *                Credentials are never stored in this repository.
 */

$config = [
    "host"     => "localhost",
    "dbname"   => "studyhive_db",
    "username" => "root",
    "password" => "",
];

$serverConfigFile = "/etc/studyhive/config.php";

if (is_readable($serverConfigFile)) {
    $config = array_merge($config, require $serverConfigFile);
}

// RDS only accepts TLS connections. The server config sets "ssl_ca" to the
// AWS RDS CA bundle; locally (XAMPP) it is absent and no TLS is used.
$options = [];

if (!empty($config["ssl_ca"])) {
    // PHP 8.4+ renamed the constant; the old name is deprecated in 8.5.
    $sslCaAttr = defined("Pdo\\Mysql::ATTR_SSL_CA")
        ? constant("Pdo\\Mysql::ATTR_SSL_CA")
        : PDO::MYSQL_ATTR_SSL_CA;
    $options[$sslCaAttr] = $config["ssl_ca"];
}

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config["username"],
        $config["password"],
        $options
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit("Unable to connect to the database.");
}

unset($config, $serverConfigFile, $options, $sslCaAttr);

?>
