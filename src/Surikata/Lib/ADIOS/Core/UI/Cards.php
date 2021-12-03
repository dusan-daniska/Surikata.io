<?php

namespace ADIOS\Core\UI;

/**
 * Renders Card-based list of elements.
 *
 * @package UI\Elements
 */
class Cards extends \ADIOS\Core\UI\View {
  public function render(string $panel = "") {
    $model = $this->adios->getModel($this->params['model']);

    $params = $model->cardsParams($this->params);
    $model->tmpCardParams = $params;

    switch ($params['columns'] ?? 3) {
      case 1: default: $bootstrapColumnSize = 12; break;
      case 2: $bootstrapColumnSize = 6; break;
      case 3: $bootstrapColumnSize = 4; break;
      case 4: $bootstrapColumnSize = 3; break;
      case 6: $bootstrapColumnSize = 2; break;
    }
    
    $cards = $model->getWithLookups(function($model, $query) {
      if (!empty($model->tmpCardParams['where'])) {
        $query = $query->whereRaw($model->tmpCardParams['where']);
      }
      return $query;
    });

    $html = "";
    // REVIEW: nie je lepsie takyto zapis?
    // if ($params['show_add_button'] ?? FALSE) {
    // Takyto zapis generuje zbytocny varning do logu ak v poli hladany kluc chyba
    if(!array_key_exists('show_add_button', $params) or $params['show_add_button'] !== FALSE) {
      $html .= "
        <div class='row mb-3'>
          ".$this->adios->ui->Button([
            "type" => "add",
            "onclick" => "window_render('".$model->getFullUrlBase($this->params)."/Add');"
          ])->render()."
        </div>
      ";
    }

    // REVIEW: poznamka - pridavanie asi bude fungovat, ale refreshovanie cards po pridani
    // nefunguje. To je known bug aj po editacii - napr. pri editacii galerie produktov v
    // karte produktu sa cards nerefreshnu.
    // Ja som riesil iba to, aby sa button nezobrazoval, ked nema zmysel. Neriesil som pridávanie, to tam uz bolo.

    $html .= "<div class='row'>";
    foreach ($cards as $card) {
      $html .= "
        <div class='col-lg-{$bootstrapColumnSize} col-md-12'>
          ".$model->cardsCardHtmlFormatter($card)."
        </div>
      ";
    }
    $html .= "
      </div>
    ";

    if ($this->params['__IS_WINDOW__']) {
      $html = $this->adios->ui->Window([
        'content' => $html,
        'titleRaw' => $params['window']['titleRaw'],
        'title' => $params['window']['title'],
        'subtitle' => $params['window']['subtitle'],
      ])->render();
    }

    return $html;
  }
}
