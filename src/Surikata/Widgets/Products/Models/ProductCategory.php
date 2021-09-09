<?php

namespace ADIOS\Widgets\Products\Models;

class ProductCategory extends \ADIOS\Core\Model {
  var $sqlName = "products_categories";
  var $lookupSqlValue = "{%TABLE%}.name_lang_1";
  var $urlBase = "Products/Categories";
  var $tableTitle = "Product categories";
  var $formTitleForInserting = "New product category";
  var $formTitleForEditing = "Product category";

  public function columns(array $columns = []) {
    $translatedColumns = [];
    $domainLanguages = $this->adios->config['widgets']['Website']['domainLanguages'];

    foreach ($domainLanguages as $languageIndex => $languageName) {
      $translatedColumns["name_lang_{$languageIndex}"] = [
        "type" => "varchar",
        "title" => $this->translate("Name")." ({$languageName})",
        "show_column" => ($languageIndex == 1),
        "is_searchable" => ($languageIndex == 1),
      ];
      $translatedColumns["description_lang_{$languageIndex}"] = [
        "type" => "text",
        "title" => $this->translate("Description")." ({$languageName})",
        "interface" => "formatted_text",
        "show_column" => ($languageIndex == 1),
        "is_searchable" => ($languageIndex == 1),
      ];
    }

    return parent::columns(array_merge(
      $translatedColumns,
      [
        "id_parent" => [
          "type" => "lookup",
          "model" => "Widgets/Products/Models/ProductCategory",
          "order_column" => "order_index",
          "title" => "Parent category",
          "show_column" => true
        ],

        "order_index" => [
          "type" => "int",
          "title" => "Order index",
          "show_column" => TRUE,
        ],

        "tree_left_index" => [
          "type" => "int",
          "title" => "Tree left index",
          "readonly" => TRUE,
          "show_column" => FALSE,
        ],

        "tree_right_index" => [
          "type" => "int",
          "title" => "Tree right index",
          "readonly" => TRUE,
          "show_column" => FALSE,
        ],

        // "code" => [
        //   "type" => "varchar",
        //   "title" => "Code",
        //   "show_column" => TRUE,
        // ],

        "image" => [
          "type" => "image",
          "title" => "Image",
          "required" => FALSE,
          "show_column" => TRUE,
        ],

        "is_highlighted" => [
          "type" => "boolean",
          "title" => "Highlight category",
        ],

      ]
    ));
  }

  public function routing(array $routing = []) {
    return parent::routing([
      '/^Products\/Categories\/Tree$/' => [
        "action" => "UI/Tree",
        "params" => [
          "model" => "Widgets/Products/Models/ProductCategory",
        ]
      ],
      '/^Products\/Categories\/(\d+)\/Add$/' => [
        "action" => "UI/Form",
        "params" => [
          "model" => "Widgets/Products/Models/ProductCategory",
          "id_parent" => '$1',
        ]
      ],
    ]);
  }

  public function formParams($data, $params) {
    $params['default_values'] = [
      'id_parent' => $params['id_parent']
    ];

    if ($data['id'] > 0) {
      $params['title'] = $data['name_lang_1'];
      $params['subtitle'] = "Product category";
    }

    $params['columns']['id_parent']['readonly'] = $params['id_parent'] > 0;

    $tabTranslations = [];
    $domainLanguages = $this->adios->config['widgets']['Website']['domainLanguages'];

    $i = 1;
    foreach ($domainLanguages as $languageIndex => $languageName) {
      if ($i > 1) {
        $tabTranslations[] = ["html" => "<b>".hsc($languageName)."</b>"];
        $tabTranslations[] = "name_lang_{$languageIndex}";
        $tabTranslations[] = "description_lang_{$languageIndex}";
      }
      $i++;
    }

    if (count($tabTranslations) == 0) {
      $tabTranslations[] = ["html" => "No translations available."];
    }

    $params["template"] = [
      "columns" => [
        [
          "class" => "col-md-9 pl-0",
          "tabs" => [
            $this->translate("General") => [
              "name_lang_1",
              "description_lang_1",
              "image",
            ],
            $this->translate("Translations") => $tabTranslations,
            $this->translate("Miscelaneous") => [
              "id_parent",
              "order_index",
              "is_highlighted",
            ],
          ],
        ],
      ],
    ];

    return $params;
  }

  public function getAll(string $keyBy = "") {
    return $this->associateKey(
      $this->fetchQueryAsArray($this->getQuery('*')),
      'id'
    );
  }

  public function getAllCached() {
    if ($this->allCategoriesCache === NULL) {
      $this->allCategoriesCache = $this->getAll(); // TODO: UPPERCASE LOOKUP
    }

    return $this->allCategoriesCache;
  }

  public function translateForWeb($categories, $languageIndex) {
    foreach ($categories as $key => $value) {
      $categories[$key]["TRANSLATIONS"]["name"] = $value["name_lang_{$languageIndex}"];
    }

    return $categories;
  }

  public function getCatalogInfo($idCategory, $page = 0, $itemsPerPage = 0, $filter = NULL, $languageIndex = 1) {
    $idCategory = (int) $idCategory;
    $languageIndex = (int) $languageIndex;

    if ($page < 1) $page = 1;
    if ($itemsPerPage < 1) $itemsPerPage = 1;
    if (!is_array($filter)) $filter = [];

    $catalogInfo = [];

    ////////////////////////////////////////
    // info about categories

    $allCategories = $this->translateForWeb($this->getAllCached(), $languageIndex); // TODO: UPPERCASE LOOKUP

    $allSubCategories = $this->extractAllSubCategories($idCategory, $allCategories);
    // $directSubCategories = $this->extractDirectSubCategories($idCategory, $allCategories);
    $catalogInfo["category"] = $allCategories[$idCategory];

    $categoryIdsToBrowse = array_keys($allSubCategories);
    $categoryIdsToBrowse[] = $idCategory;

    $start = microtime(true);

    ////////////////////////////////////////
    // info about products

    $productModel = new \ADIOS\Widgets\Products\Models\Product($this->adios);

    $productsQuery = $productModel->getQuery();

    // id_brand lookup information will be used in extracting information about brands
    $productModel->addLookupsToQuery($productsQuery, ['id_brand' => 'id_brand']);

    if ($idCategory > 0) {
      // not adding where condition if all products should be retreived,
      // the condition slows down the query
      $productsQuery->whereIn('id_category', $categoryIdsToBrowse);
    }

    if (!empty($filter['filteredBrands'])) {
      $productsQuery->whereIn('id_brand', $filter['filteredBrands']);
    }

    if (array_key_exists("sort", $filter)) {
      $sortType = $filter["sort"];
      $sortDesc = strpos($sortType, "desc") !== false ? "DESC" : "ASC";
      switch ($sortType) {
        case "price":
        case "price_desc":
          $productsQuery->orderBy('sale_price', $sortDesc);
            break;
        case "title":
        case "title_desc":
          $productsQuery->orderBy('name_lang_1', $sortDesc);
          break;
        case "date":
        case "date_desc":
          $productsQuery->orderBy('id', $sortDesc);
          break;
      }
    }

    $allProducts = $this->fetchQueryAsArray($productsQuery, 'id', FALSE);
    $catalogInfo["productCount"] = count($allProducts);

    $productModel->addLookupsToQuery($productsQuery);
    $productsQuery->skip(($page - 1) * $itemsPerPage);
    $productsQuery->take($itemsPerPage);

    $catalogInfo["products"] = $this->fetchQueryAsArray($productsQuery); // TODO: UPPERCASE LOOKUP
    $catalogInfo["products"] = $productModel->translateForWeb($catalogInfo["products"], $languageIndex);

    ////////////////////////////////////////
    // info about prices

    $priceInfo = $productModel->getPriceInfoForListOfProducts(array_keys($catalogInfo["products"]));
    foreach ($priceInfo as $tmpIdProduct => $tmpPriceInfo) {
      $catalogInfo["products"][$tmpIdProduct]["price"] = $tmpPriceInfo;
    }

    ////////////////////////////////////////
    // info about parameters

    $parameterModel = $this->adios->getModel("Widgets/Products/Models/ProductFeatureAssignment");

    $parametersQuery = $parameterModel->getQuery();
    if ($idCategory > 0) {
      $parameterModel->addLookupsToQuery($parametersQuery, ['id_feature' => 'ProductFeature', 'id_product' => 'Product']);
      $parametersQuery->whereIn($productModel->table.'.id_category', $categoryIdsToBrowse);
    } else {
      // 1. not adding where condition if all products should be retreived,
      // the condition slows down the query
      // 2. do not need information about products - not filtering by product's category
      $parameterModel->addLookupsToQuery($parametersQuery, ['id_feature' => 'ProductFeature']);
    }

    $allParameters = $this->fetchQueryAsArray($parametersQuery, FALSE, FALSE);
    $catalogInfo["availableProductParameters"] = $this->extractLookupFromQueryResult($allParameters, 'ProductFeature'); // TODO: UPPERCASE LOOKUP
    $catalogInfo["availableProductParameters"] = $this->associateKey($catalogInfo["availableProductParameters"], 'id');

    ////////////////////////////////////////
    // info about brands

    // $tmpBrands = [];

    // foreach ($allProducts as $tmpProduct) {
    //   if (!empty($tmpProduct['Brand___LOOKUP___id'])) {
    //     $tmpBrands[$tmpProduct['Brand___LOOKUP___id']] = $tmpProduct;
    //   }
    // }
    // $tmpBrands = $this->processLookupsInQueryResult($tmpBrands);
    // foreach ($tmpBrands as $key => $value) {
    //   $catalogInfo["availableProductBrands"][] = $value['Brand'];
    // }

    // var_dump(round((microtime(true) - $start) * 1000, 2));
    // // var_dump(reset($tmpBrands));
    // var_dump(($catalogInfo["availableProductBrands"]));
    // var_dump(array_keys($catalogInfo["allSubCategories"]));
    // exit();

    // var_dump($catalogInfo["availableProductParameters"]);exit();

    return $catalogInfo;
  }

  public function getById(int $idCategory) {
    return reset($this
      ->where('id', $idCategory)
      ->get()
      ->toArray()
    );
  }

  public function extractDirectSubCategories($idCategory, $allCategories) {
    $directSubCategories = [];

    foreach ($allCategories as $key => $category) {
      if ($category['id_parent'] == $idCategory) {
        $directSubCategories[$key] = $category;
      }
    }

    return $directSubCategories;
  }

  public function extractAllSubCategories($idCategory, $allCategories) {
    $allSubCategories = $this->extractDirectSubCategories($idCategory, $allCategories);

    $tmp = $allSubCategories;
    foreach ($tmp as $category) {
      $tmpSub = $this->extractAllSubCategories($category['id'], $allCategories);
      if (count($tmpSub) > 0) {
        $allSubCategories = array_merge($allSubCategories, $tmpSub);
      }
    }

    return $allSubCategories;
  }

  public function extractParentCategories($idCategory, $allCategories) {
    $parentCategories = [];

    $tmpCategory = $allCategories[$idCategory];
    while ($tmpCategory["id_parent"] > 0) {
      $parentCategories[] = $tmpCategory;
      $tmpCategory = $allCategories[$tmpCategory["id_parent"]];
    }

    $parentCategories[] = $tmpCategory;

    return $parentCategories;
  }

  public function getAllCategoriesAndSubCategories($allCategories) {
    $items = [];

    foreach ($allCategories as $item) {

      if ($item['id_parent'] == 0) {
        $children = [];
        
        foreach ($allCategories as $itemSub) {
          if ($itemSub['id_parent'] == $item['id']) {
            $children[] = $itemSub;
          }
        }
        
        $items[] = [
          "id" => $item["id"],
          "name" => $item["TRANSLATIONS"]["name"],
          "subCategory" => $children,
          "url" => $item["url"]
        ];
      }

    }

    return $items;
  }

  public function onAfterSave($data, $returnValue) {
    $this->adios->widgets['Website']->rebuildSitemapForAllDomains();

    return parent::onAfterSave($data, $returnValue);
  }

  public function breadcrumbs(int $idCategory, $allCategories, $level = 0) {
    $category = $allCategories[$idCategory];
    $breadcrumbs = [];

    $breadcrumbs[] = $category;

    if ((int) $category['id_parent'] > 0 && (int) $category['id_parent'] != $idCategory) {
      $breadcrumbs = array_merge(
        $breadcrumbs,
        $this->breadcrumbs((int) $category['id_parent'], $allCategories, $level + 1)
      );
    }

    if ($level == 0) {
      return array_reverse($breadcrumbs);
    } else {
      return $breadcrumbs;
    }
  }

}