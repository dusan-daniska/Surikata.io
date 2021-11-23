<?php

class InstallerHelperFunctions {
  public static function parseDomainsToInstall($installationConfig) {
    $domainsToInstall = [];
    
    for ($i = 1; $i <= 3; $i++) {
      if (!empty($installationConfig["domain_{$i}_description"])) {
        $domainsToInstall[$i] = [
          "name" => \ADIOS\Core\HelperFunctions::str2url($installationConfig["domain_{$i}_description"]),
          "description" => $installationConfig["domain_{$i}_description"],
          "slug" => $installationConfig["domain_{$i}_slug"],
          "themeName" => $installationConfig["domain_{$i}_theme_name"],
          "languageIndex" => $installationConfig["domain_{$i}_language_index"],
        ];
      }
    }

    return $domainsToInstall;
  }

  public static function renderConfigEnvDomains($domainsToInstall = []) {
    $configEnvDomainsPHP = "<?php /* This file is auto-generated by Surikata.io installer */\r\n";
    $configEnvDomainsPHP .= "\r\n";
    $configEnvDomainsPHP .= "\$configEnv['domainLanguages'] = [1 => 'English', 2 => 'Slovensky', 3 => 'Česky'];\r\n";
    $configEnvDomainsPHP .= "\r\n";

    if (count($domainsToInstall) > 0) {
      $configEnvDomainsPHP .= '$configEnv["domains"] = ['."\r\n";
      foreach ($domainsToInstall as $domain) {
        $configEnvDomainsPHP .= "  [\r\n";
        $configEnvDomainsPHP .= "    'name' => '{$domain['name']}',\r\n";
        $configEnvDomainsPHP .= "    'description' => '{$domain['description']}',\r\n";
        $configEnvDomainsPHP .= "    'slug' => '{$domain['slug']}',\r\n";
        $configEnvDomainsPHP .= "    'rootUrl' => \$_SERVER['HTTP_HOST'].REWRITE_BASE.'{$domain['slug']}',\r\n";
        $configEnvDomainsPHP .= "    'languageIndex' => {$domain['languageIndex']},\r\n";
        $configEnvDomainsPHP .= "  ],\r\n";
      }
      $configEnvDomainsPHP .= "];\r\n";
      $configEnvDomainsPHP .= "\r\n";

      $configEnvDomainsPHP .= trim('
  $re = "/^".str_replace("/", "\\/", REWRITE_BASE)."/";
  $tmp = preg_replace($re, "", $_SERVER["REQUEST_URI"]);
  $tmppos = strpos($tmp, "/");
  $slug = ($tmppos === FALSE ? $tmp : substr($tmp, 0, $tmppos));

  $domainToRender = reset($configEnv["domains"]);
  foreach ($configEnv["domains"] as $domain) {
    if ($domain["slug"] == $slug) {
      $domainToRender = $domain;
    }
  }

  define("WEBSITE_DOMAIN_TO_RENDER", $domainToRender["name"]);
  define("WEBSITE_REWRITE_BASE", REWRITE_BASE.$domainToRender["slug"]."/");
      ');
    }

    return $configEnvDomainsPHP;
  }
}