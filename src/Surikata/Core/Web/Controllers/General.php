<?php

namespace Surikata\Core\Web\Controllers;

class General extends \Surikata\Core\Web\Controller {

  public function renderPlugin($pluginName, $pluginSettings, $panelName = "") {
    $plugin = $this->websiteRenderer->getPlugin($pluginName);

    $this->websiteRenderer->logTimestamp("renderPlugin {$pluginName} start");

    $renderParams = array_merge(
      [
        "panel" => $panelName,
        "pluginName" => $pluginName,
      ],
      $pluginSettings ?? [],
      $plugin->getTwigParams($pluginSettings ?? []),
      $this->websiteRenderer->twigParams,
    );
    $this->websiteRenderer->logTimestamp("renderPlugin {$pluginName} #1");

    $renderParams = $this->adminPanel->dispatchEventToPlugins("onGeneralControllerAfterRenderPlugin", [
      "controller" => $this,
      "pluginName" => $pluginName,
      "pluginSettings" => $pluginSettings,
      "panelName" => $panelName,
      "renderParams" => $renderParams,
    ])["renderParams"];
    $this->websiteRenderer->logTimestamp("renderPlugin {$pluginName} #2");

    if (is_object($plugin)) {
      $this->websiteRenderer->currentRenderedPlugin = $plugin;

      if (($this->websiteRenderer->urlVariables["__output"] ?? "") == "json") {
        $html = json_encode($plugin->renderJSON($renderParams));
      } else {
        $templateFile = "{$this->websiteRenderer->themeDir}/Templates/Plugins/{$pluginName}.twig";

        if (is_file($templateFile)) {
          $renderParams["system"]["availableVariables"] = array_keys($renderParams);

          $this->websiteRenderer->currentRenderedPlugin->twigRenderParams = $renderParams;
          $this->websiteRenderer->logTimestamp("renderPlugin {$pluginName} #3 $pluginName.twig");

          $html = $this->websiteRenderer->twig
            ->render("Templates/Plugins/{$pluginName}.twig", $renderParams)
          ;
          $this->websiteRenderer->logTimestamp("renderPlugin {$pluginName} #4");

        } else if (($tmpRawHtml = $plugin->render($renderParams)) !== NULL) {
          $html = $tmpRawHtml;
        } else {
          $html = "Template for plugin {$pluginName} not found.";
        }
      }

      $this->websiteRenderer->currentRenderedPlugin = NULL;
    }

    $this->websiteRenderer->logTimestamp("renderPlugin {$pluginName} end");

    return $html;
  }

  public function renderPlugins($contentStructure = []) {

    // rozparsujem nastavenia layoutu
    if (is_array($contentStructure)) {

      // inicializacia TWIG parametrov; urcite budem potrebovat
      // {{ panels.NAZOV_PANELU.NEJAKE_NASTAVENIE }}

      $twigParams = [
        "panels" => [],
      ];

      // vygenerujem HTML kazdeho panelu v layoute, ak ma panel
      // nastaveny plugin, ktory sa ma v nom zobrazit.
      // Pri renderovani pluginu sa pouziva kombinacia parametrov:
      //   - {{ panel }} = nazov panelu
      //       toto umozni pri renderovani pluginu vziat do uvahy, ci
      //       je potrebne HTML napr. pre sidebar alebo main_content
      //   - nastavenia od pouzivatela ($panelSettings['settings'])
      //   - parametre vygenerovane metodou $plugin->getTwigParams()
      //   - uz vygenerovane TwigParams v ramci CASCADA Controllers,
      //       napr. rootUrl, pageUrl a pod. ($this->twigParams)

      foreach ($contentStructure as $panelName => $panelSettings) {
        if (!empty($panelSettings["plugin"])) {
          $tmpHtml = $this->renderPlugin(
            $panelSettings["plugin"],
            $panelSettings['settings'],
            $panelName
          );

          $pluginObject = $this->adminPanel->getPlugin($panelSettings["plugin"]);
          $manifest = $pluginObject->manifest();
          $pluginTitle = $manifest["title"];
        } else {
          $tmpHtml = "";
          $pluginTitle = "";
        }

        if ($this->websiteRenderer->visualContentEditorEnabled) {
          $tmpHtml = "
            <div
              class='sio-edit-mode-panel'
              data-panel-name='".ads($panelName)."'
              data-iframe-token='".ads($this->websiteRenderer->visualContentEditorIframeToken)."'
              title='".ads($panelName)."'
              onclick='
                window.parent.postMessage(
                  {
                    initiator: \"visualContentEditor\",
                    action: \"openPanel\",
                    panelName: $(this).data(\"panel-name\"),
                    iframeToken: $(this).data(\"iframe-token\"),
                  }
                );

                event.stopPropagation();
                return false;
              '
            >
              <div class='sio-edit-mode-info-tag'>Panel: ".hsc($panelName)."</div>
              <div class='sio-edit-mode-info-tag'>Plugin: ".hsc("{$pluginTitle} ({$panelSettings["plugin"]})")."</div>
              {$tmpHtml}
            </div>
          ";
        }

        $twigParams["plugins"][$panelSettings["plugin"]]["html"] = $tmpHtml;
        $twigParams["panels"][$panelName]["html"] = $tmpHtml;
      }

      // pripravene TwigParams podsuniem do CASCADy na renderovanie
      // layout .twig sablony

      $this->websiteRenderer->setTwigParams($twigParams);
    }
  }

  public function preRender() {
    // v tomto momente uz CASCADA router zistil, aka webstranka sa ma zobrazit,
    // cize viem si vytiahnut nastavenia stranky (z GTP_web_stranky)

    $this->websiteRenderer->logTimestamp("GenCon preRender() start");

    $this->websiteRenderer->idWebPage = $this->websiteRenderer->urlVariables["idWebPage"] ?? 0;
    $this->websiteRenderer->visualContentEditorEnabled = (bool) ($this->websiteRenderer->urlVariables["_vce"] ?? FALSE);
    $this->websiteRenderer->visualContentEditorIframeToken = ($this->websiteRenderer->urlVariables["_vcetkn"] ?? "");
    $this->websiteRenderer->currentPage = $this->websiteRenderer->pages[$this->websiteRenderer->idWebPage] ?? NULL;

    // Dusan 30.1.2022: Toto sposobovalo problemy pri URLkach vytvaranych cez event
    // onAfterSiteMap, pretoze tieto sa nenachadzaju v databaze => funkcia loadPublishedPages()
    // pouzivana na nacitanie $this->websiteRenderer->pages takuto URLku neevidovala.
    // Paradoxne, zakomentovanie tohoto kodu sfunkcnilo 404 not found stranku.

    // if ($this->websiteRenderer->currentPage === NULL) {
    //   header("HTTP/1.1 302 Moved Temporarily");
    //   header("Location: {$this->websiteRenderer->rewriteBase}");
    //   exit();
    // }

    $this->websiteRenderer->onGeneralControllerAfterRouting() ;

    $this->adminPanel->dispatchEventToPlugins("onGeneralControllerPreRender", [
      "controller" => $this,
    ]);

    $this->websiteRenderer->setTwigParams(
      $this->websiteRenderer->getGlobalTwigParams()
    );

    $adminPanelConfig = $this->websiteRenderer->adminPanel->config;
    $maintenanceSettings = $adminPanelConfig["settings"]["web"]["maintenance"] ?? [];
    $maintenanceModeActivated = (bool) ($maintenanceSettings["activated"] ?? FALSE);

    if ($maintenanceModeActivated) {
      $this->websiteRenderer->outputHtml =
        $this->websiteRenderer->twig->render(
          "{$this->websiteRenderer->twigTemplatesSubDir}/Maintenance.twig",
          array_merge(
            $this->websiteRenderer->twigParams,
            [
              "additionalInfo" => $maintenanceSettings["additionalInfo"]
            ]
          )
        );
      $this->websiteRenderer->cancelRendering();
    }

    if (empty($_GET['__renderOnlyPlugin'])) {
      if (is_array($this->websiteRenderer->contentStructure)) {
        $contentStructure = $this->websiteRenderer->contentStructure;
      } else {
        $tmp = @json_decode(
          ($this->websiteRenderer->currentPage['content_structure'] ?? ""),
          TRUE
        );
        $contentStructure = $tmp["panels"] ?? [];
      }

      $this->renderPlugins($contentStructure);
    } else {
      $pluginName = $_GET['__renderOnlyPlugin'];
      $pluginSettings = $this->websiteRenderer->getCurrentPagePluginSettings($pluginName);

      $this->websiteRenderer->outputHtml = $this->renderPlugin($pluginName, $pluginSettings);
      $this->websiteRenderer->cancelRendering();
    }

    $this->websiteRenderer->logTimestamp("GenCon preRender() end");

  }

}
