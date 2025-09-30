### Server endpoints

**Source: api_platform**

| Method | Path | Route | Controller | OpenAPI opId | Params |
|---|---|---|---|---|---|
| GET | /api/.well-known/genid/{id} | api_genid | api_platform.action.not_exposed::__invoke |  |  |
| HEAD | /api/.well-known/genid/{id} | api_genid | api_platform.action.not_exposed::__invoke |  |  |
| GET | /api/{index}.{_format} | api_entrypoint | api_platform.action.entrypoint::__invoke |  |  |
| HEAD | /api/{index}.{_format} | api_entrypoint | api_platform.action.entrypoint::__invoke |  |  |
| GET | /api/admin/fias | _api_/admin/fias_get_collection | api_platform.symfony.main_controller::__invoke | api_adminfias_get_collection | ?page ?itemsPerPage ?pagination ?offname ?shortname ?postalcode ?level ?level[] ?parentId ?parentId[] |
| GET | /api/admin/fias/{id} | _api_/admin/fias/{id}_get | api_platform.symfony.main_controller::__invoke | api_adminfias_id_get | id |
| GET | /api/admin/order-statuses | _api_/admin/order-statuses_get_collection | api_platform.symfony.main_controller::__invoke | api_adminorder-statuses_get_collection |  |
| POST | /api/admin/order-statuses | _api_/admin/order-statuses_post | api_platform.symfony.main_controller::__invoke | api_adminorder-statuses_post |  |
| DELETE | /api/admin/order-statuses/{id} | _api_/admin/order-statuses/{id}_delete | api_platform.symfony.main_controller::__invoke | api_adminorder-statuses_id_delete | id |
| GET | /api/admin/order-statuses/{id} | _api_/admin/order-statuses/{id}_get | api_platform.symfony.main_controller::__invoke | api_adminorder-statuses_id_get | id |
| PATCH | /api/admin/order-statuses/{id} | _api_/admin/order-statuses/{id}_patch | api_platform.symfony.main_controller::__invoke | api_adminorder-statuses_id_patch | id |
| PUT | /api/admin/order-statuses/{id} | _api_/admin/order-statuses/{id}_put | api_platform.symfony.main_controller::__invoke | api_adminorder-statuses_id_put | id |
| GET | /api/admin/pvz-points | _api_/admin/pvz-points_get_collection | api_platform.symfony.main_controller::__invoke | api_adminpvz-points_get_collection | ?page ?itemsPerPage ?pagination ?city ?cityFias.id ?cityFias.id[] ?region |
| DELETE | /api/admin/pvz-points/{id} | _api_/admin/pvz-points/{id}_delete | api_platform.symfony.main_controller::__invoke | api_adminpvz-points_id_delete | id |
| GET | /api/admin/pvz-points/{id} | _api_/admin/pvz-points/{id}_get | api_platform.symfony.main_controller::__invoke | api_adminpvz-points_id_get | id |
| PATCH | /api/admin/pvz-points/{id} | _api_/admin/pvz-points/{id}_patch | api_platform.symfony.main_controller::__invoke | api_adminpvz-points_id_patch | id |
| PUT | /api/admin/pvz-points/{id} | _api_/admin/pvz-points/{id}_put | api_platform.symfony.main_controller::__invoke | api_adminpvz-points_id_put | id |
| GET | /api/admin/pvz-prices | _api_/admin/pvz-prices_get_collection | api_platform.symfony.main_controller::__invoke | api_adminpvz-prices_get_collection | ?page ?itemsPerPage ?pagination ?city ?cityFias.id ?cityFias.id[] ?region |
| DELETE | /api/admin/pvz-prices/{id} | _api_/admin/pvz-prices/{id}_delete | api_platform.symfony.main_controller::__invoke | api_adminpvz-prices_id_delete | id |
| GET | /api/admin/pvz-prices/{id} | _api_/admin/pvz-prices/{id}_get | api_platform.symfony.main_controller::__invoke | api_adminpvz-prices_id_get | id |
| PATCH | /api/admin/pvz-prices/{id} | _api_/admin/pvz-prices/{id}_patch | api_platform.symfony.main_controller::__invoke | api_adminpvz-prices_id_patch | id |
| PUT | /api/admin/pvz-prices/{id} | _api_/admin/pvz-prices/{id}_put | api_platform.symfony.main_controller::__invoke | api_adminpvz-prices_id_put | id |
| GET | /api/admin/settings | _api_/admin/settings_get_collection | api_platform.symfony.main_controller::__invoke | api_adminsettings_get_collection |  |
| POST | /api/admin/settings | _api_/admin/settings_post | api_platform.symfony.main_controller::__invoke | api_adminsettings_post |  |
| DELETE | /api/admin/settings/{id} | _api_/admin/settings/{id}_delete | api_platform.symfony.main_controller::__invoke | api_adminsettings_id_delete | id |
| GET | /api/admin/settings/{id} | _api_/admin/settings/{id}_get | api_platform.symfony.main_controller::__invoke | api_adminsettings_id_get | id |
| PATCH | /api/admin/settings/{id} | _api_/admin/settings/{id}_patch | api_platform.symfony.main_controller::__invoke | api_adminsettings_id_patch | id |
| PUT | /api/admin/settings/{id} | _api_/admin/settings/{id}_put | api_platform.symfony.main_controller::__invoke | api_adminsettings_id_put | id |
| GET | /api/attribute_groups.{_format} | _api_/attribute_groups{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_attribute_groups_get_collection | ?page ?itemsPerPage |
| POST | /api/attribute_groups.{_format} | _api_/attribute_groups{._format}_post | api_platform.symfony.main_controller::__invoke | api_attribute_groups_post |  |
| DELETE | /api/attribute_groups/{id}.{_format} | _api_/attribute_groups/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_attribute_groups_id_delete | id |
| GET | /api/attribute_groups/{id}.{_format} | _api_/attribute_groups/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_attribute_groups_id_get | id |
| PATCH | /api/attribute_groups/{id}.{_format} | _api_/attribute_groups/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_attribute_groups_id_patch | id |
| GET | /api/attributes.{_format} | _api_/attributes{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_attributes_get_collection | ?page ?itemsPerPage |
| POST | /api/attributes.{_format} | _api_/attributes{._format}_post | api_platform.symfony.main_controller::__invoke | api_attributes_post |  |
| DELETE | /api/attributes/{id}.{_format} | _api_/attributes/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_attributes_id_delete | id |
| GET | /api/attributes/{id}.{_format} | _api_/attributes/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_attributes_id_get | id |
| PATCH | /api/attributes/{id}.{_format} | _api_/attributes/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_attributes_id_patch | id |
| GET | /api/carousels.{_format} | _api_/carousels{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_carousels_get_collection | ?page ?itemsPerPage ?place ?place[] |
| DELETE | /api/carousels/{id}.{_format} | _api_/carousels/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_carousels_id_delete | id |
| GET | /api/carousels/{id}.{_format} | _api_/carousels/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_carousels_id_get | id |
| PATCH | /api/carousels/{id}.{_format} | _api_/carousels/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_carousels_id_patch | id |
| DELETE | /api/cart | api_cart_clear | App\Controller\Api\CartApiController::clearCart |  |  |
| GET | /api/cart | api_cart_get | App\Controller\Api\CartApiController::getCart |  |  |
| PATCH | /api/cart | api_cart_update_pricing_policy | App\Controller\Api\CartApiController::updatePricingPolicy |  |  |
| POST | /api/cart/batch | api_cart_batch | App\Controller\Api\CartApiController::batch |  |  |
| POST | /api/cart/items | api_cart_add_item | App\Controller\Api\CartApiController::addItem |  |  |
| DELETE | /api/cart/items/{itemId} | api_cart_remove_item | App\Controller\Api\CartApiController::removeItem |  |  |
| PATCH | /api/cart/items/{itemId} | api_cart_update_qty | App\Controller\Api\CartApiController::updateQty |  |  |
| GET | /api/cart/products/{productId}/options | api_product_options | App\Controller\Api\CartApiController::getProductOptions |  |  |
| POST | /api/cart/reprice | api_cart_reprice | App\Controller\Api\CartApiController::repriceCart |  |  |
| GET | /api/categories.{_format} | _api_/categories{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_categories_get_collection | ?page ?itemsPerPage |
| POST | /api/categories.{_format} | _api_/categories{._format}_post | api_platform.symfony.main_controller::__invoke | api_categories_post |  |
| DELETE | /api/categories/{id}.{_format} | _api_/categories/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_categories_id_delete | id |
| GET | /api/categories/{id}.{_format} | _api_/categories/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_categories_id_get | id |
| PATCH | /api/categories/{id}.{_format} | _api_/categories/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_categories_id_patch | id |
| POST | /api/checkout/draft | api_checkout_draft_save | App\Controller\Api\CheckoutDraftController::saveDraft |  |  |
| GET | /api/cities.{_format} | _api_/cities{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_cities_get_collection | ?page ?itemsPerPage ?pagination ?order[population] ?address |
| POST | /api/cities.{_format} | _api_/cities{._format}_post | api_platform.symfony.main_controller::__invoke | api_cities_post |  |
| DELETE | /api/cities/{id}.{_format} | _api_/cities/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_cities_id_delete | id |
| GET | /api/cities/{id}.{_format} | _api_/cities/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_cities_id_get | id |
| PATCH | /api/cities/{id}.{_format} | _api_/cities/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_cities_id_patch | id |
| GET | /api/city_modals.{_format} | _api_/city_modals{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_city_modals_get_collection | ?page ?itemsPerPage |
| GET | /api/city_modals/{id}.{_format} | _api_/city_modals/{id}{._format}_get | api_platform.action.not_exposed::__invoke |  |  |
| GET | /api/config/pagination | api_config_pagination | App\Controller\ConfigController::getPaginationConfig |  |  |
| GET | /api/config/pagination/city | api_config_pagination_city | App\Controller\ConfigController::getCityPaginationConfig |  |  |
| GET | /api/config/pagination/pvz | api_config_pagination_pvz | App\Controller\ConfigController::getPvzPaginationConfig |  |  |
| GET | /api/contexts/{shortName}.{_format} | api_jsonld_context | api_platform.jsonld.action.context::__invoke |  |  |
| HEAD | /api/contexts/{shortName}.{_format} | api_jsonld_context | api_platform.jsonld.action.context::__invoke |  |  |
| GET | /api/csrf | api_csrf_token | App\Controller\Api\CsrfController::token |  |  |
| GET | /api/delivery_types.{_format} | _api_/delivery_types{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_delivery_types_get_collection |  |
| POST | /api/delivery_types.{_format} | _api_/delivery_types{._format}_post | api_platform.symfony.main_controller::__invoke | api_delivery_types_post |  |
| DELETE | /api/delivery_types/{id}.{_format} | _api_/delivery_types/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_delivery_types_id_delete | id |
| GET | /api/delivery_types/{id}.{_format} | _api_/delivery_types/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_delivery_types_id_get | id |
| PATCH | /api/delivery_types/{id}.{_format} | _api_/delivery_types/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_delivery_types_id_patch | id |
| GET | /api/delivery/context | api_delivery_context | App\Controller\Api\DeliveryApiController::context |  |  |
| GET | /api/delivery/pvz-points | api_delivery_pvz_points | App\Controller\Api\DeliveryApiController::pvzPoints |  |  |
| POST | /api/delivery/select-city | api_delivery_select_city | App\Controller\Api\DeliveryApiController::selectCity |  |  |
| POST | /api/delivery/select-method | api_delivery_select_method | App\Controller\Api\DeliveryApiController::selectMethod |  |  |
| POST | /api/delivery/select-pvz | api_delivery_select_pvz | App\Controller\Api\DeliveryApiController::selectPvz |  |  |
| GET | /api/docs.{_format} | api_doc | api_platform.action.documentation::__invoke |  |  |
| HEAD | /api/docs.{_format} | api_doc | api_platform.action.documentation::__invoke |  |  |
| GET | /api/errors/{status}.{_format} | _api_errors | api_platform.symfony.main_controller::__invoke |  |  |
| GET | /api/facet_configs.{_format} | _api_/facet_configs{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_facet_configs_get_collection | ?page ?itemsPerPage |
| POST | /api/facet_configs.{_format} | _api_/facet_configs{._format}_post | api_platform.symfony.main_controller::__invoke | api_facet_configs_post |  |
| GET | /api/facet_configs/{id}.{_format} | _api_/facet_configs/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_facet_configs_id_get | id |
| PATCH | /api/facet_configs/{id}.{_format} | _api_/facet_configs/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_facet_configs_id_patch | id |
| GET | /api/fias | _api_/fias_get_collection | api_platform.symfony.main_controller::__invoke | api_fias_get_collection | ?page ?itemsPerPage ?pagination ?offname ?shortname ?postalcode ?level ?level[] ?parentId ?parentId[] |
| ANY | /api/login | api_login |  |  |  |
| GET | /api/option_values.{_format} | _api_/option_values{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_option_values_get_collection | ?page ?itemsPerPage ?optionType ?optionType[] |
| POST | /api/option_values.{_format} | _api_/option_values{._format}_post | api_platform.symfony.main_controller::__invoke | api_option_values_post |  |
| DELETE | /api/option_values/{id}.{_format} | _api_/option_values/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_option_values_id_delete | id |
| GET | /api/option_values/{id}.{_format} | _api_/option_values/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_option_values_id_get | id |
| PATCH | /api/option_values/{id}.{_format} | _api_/option_values/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_option_values_id_patch | id |
| GET | /api/options.{_format} | _api_/options{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_options_get_collection | ?page ?itemsPerPage |
| POST | /api/options.{_format} | _api_/options{._format}_post | api_platform.symfony.main_controller::__invoke | api_options_post |  |
| DELETE | /api/options/{id}.{_format} | _api_/options/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_options_id_delete | id |
| GET | /api/options/{id}.{_format} | _api_/options/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_options_id_get | id |
| PATCH | /api/options/{id}.{_format} | _api_/options/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_options_id_patch | id |
| GET | /api/order_customers.{_format} | _api_/order_customers{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_order_customers_get_collection | ?page ?itemsPerPage |
| POST | /api/order_customers.{_format} | _api_/order_customers{._format}_post | api_platform.symfony.main_controller::__invoke | api_order_customers_post |  |
| DELETE | /api/order_customers/{id}.{_format} | _api_/order_customers/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_order_customers_id_delete | id |
| GET | /api/order_customers/{id}.{_format} | _api_/order_customers/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_order_customers_id_get | id |
| PATCH | /api/order_customers/{id}.{_format} | _api_/order_customers/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_order_customers_id_patch | id |
| GET | /api/order_deliveries.{_format} | _api_/order_deliveries{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_order_deliveries_get_collection | ?page ?itemsPerPage |
| POST | /api/order_deliveries.{_format} | _api_/order_deliveries{._format}_post | api_platform.symfony.main_controller::__invoke | api_order_deliveries_post |  |
| DELETE | /api/order_deliveries/{id}.{_format} | _api_/order_deliveries/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_order_deliveries_id_delete | id |
| GET | /api/order_deliveries/{id}.{_format} | _api_/order_deliveries/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_order_deliveries_id_get | id |
| PATCH | /api/order_deliveries/{id}.{_format} | _api_/order_deliveries/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_order_deliveries_id_patch | id |
| GET | /api/order_product_options.{_format} | _api_/order_product_options{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_order_product_options_get_collection | ?page ?itemsPerPage |
| POST | /api/order_product_options.{_format} | _api_/order_product_options{._format}_post | api_platform.symfony.main_controller::__invoke | api_order_product_options_post |  |
| DELETE | /api/order_product_options/{id}.{_format} | _api_/order_product_options/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_order_product_options_id_delete | id |
| GET | /api/order_product_options/{id}.{_format} | _api_/order_product_options/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_order_product_options_id_get | id |
| PATCH | /api/order_product_options/{id}.{_format} | _api_/order_product_options/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_order_product_options_id_patch | id |
| GET | /api/order_products.{_format} | _api_/order_products{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_order_products_get_collection | ?page ?itemsPerPage |
| POST | /api/order_products.{_format} | _api_/order_products{._format}_post | api_platform.symfony.main_controller::__invoke | api_order_products_post |  |
| DELETE | /api/order_products/{id}.{_format} | _api_/order_products/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_order_products_id_delete | id |
| GET | /api/order_products/{id}.{_format} | _api_/order_products/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_order_products_id_get | id |
| PATCH | /api/order_products/{id}.{_format} | _api_/order_products/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_order_products_id_patch | id |
| GET | /api/order_statuses.{_format} | _api_/order_statuses{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_order_statuses_get_collection | ?page ?itemsPerPage ?pagination |
| POST | /api/order_statuses.{_format} | _api_/order_statuses{._format}_post | api_platform.symfony.main_controller::__invoke | api_order_statuses_post |  |
| DELETE | /api/order_statuses/{id}.{_format} | _api_/order_statuses/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_order_statuses_id_delete | id |
| GET | /api/order_statuses/{id}.{_format} | _api_/order_statuses/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_order_statuses_id_get | id |
| PATCH | /api/order_statuses/{id}.{_format} | _api_/order_statuses/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_order_statuses_id_patch | id |
| PUT | /api/order_statuses/{id}.{_format} | _api_/order_statuses/{id}{._format}_put | api_platform.symfony.main_controller::__invoke | api_order_statuses_id_put | id |
| GET | /api/orders.{_format} | _api_/orders{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_orders_get_collection | ?page ?itemsPerPage ?order[dateAdded] ?orderId ?status ?status[] ?customer.name ?customer.phone |
| DELETE | /api/orders/{id}.{_format} | _api_/orders/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_orders_id_delete | id |
| GET | /api/orders/{id}.{_format} | _api_/orders/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_orders_id_get | id |
| PATCH | /api/orders/{id}.{_format} | _api_/orders/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_orders_id_patch | id |
| GET | /api/product_attribute_assignments.{_format} | _api_/product_attribute_assignments{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_product_attribute_assignments_get_collection | ?page ?itemsPerPage |
| POST | /api/product_attribute_assignments.{_format} | _api_/product_attribute_assignments{._format}_post | api_platform.symfony.main_controller::__invoke | api_product_attribute_assignments_post |  |
| DELETE | /api/product_attribute_assignments/{id}.{_format} | _api_/product_attribute_assignments/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_product_attribute_assignments_id_delete | id |
| GET | /api/product_attribute_assignments/{id}.{_format} | _api_/product_attribute_assignments/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_product_attribute_assignments_id_get | id |
| PATCH | /api/product_attribute_assignments/{id}.{_format} | _api_/product_attribute_assignments/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_product_attribute_assignments_id_patch | id |
| GET | /api/product_to_categories.{_format} | _api_/product_to_categories{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_product_to_categories_get_collection | ?page ?itemsPerPage ?product ?product[] ?category ?category[] |
| POST | /api/product_to_categories.{_format} | _api_/product_to_categories{._format}_post | api_platform.symfony.main_controller::__invoke | api_product_to_categories_post |  |
| DELETE | /api/product_to_categories/{id}.{_format} | _api_/product_to_categories/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_product_to_categories_id_delete | id |
| GET | /api/product_to_categories/{id}.{_format} | _api_/product_to_categories/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_product_to_categories_id_get | id |
| GET | /api/products.{_format} | _api_/products{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_products_get_collection | ?page ?itemsPerPage ?order[status] ?order[sortOrder] ?order[effectivePrice] ?attributeAssignments.attribute.code ?attributeAssignments.attribute.code[] ?attributeAssignments.attributeGroup.code ?attributeAssignments.attributeGroup.code[] ?category.category ?category.category[] ?attributeAssignments.boolValue ?attributeAssignments.intValue[between] ?attributeAssignments.intValue[gt] ?attributeAssignments.intValue[gte] ?attributeAssignments.intValue[lt] ?attributeAssignments.intValue[lte] ?attributeAssignments.decimalValue[between] ?attributeAssignments.decimalValue[gt] ?attributeAssignments.decimalValue[gte] ?attributeAssignments.decimalValue[lt] ?attributeAssignments.decimalValue[lte] |
| POST | /api/products.{_format} | _api_/products{._format}_post | api_platform.symfony.main_controller::__invoke | api_products_post |  |
| DELETE | /api/products/{id}.{_format} | _api_/products/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_products_id_delete | id |
| GET | /api/products/{id}.{_format} | _api_/products/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_products_id_get | id |
| PATCH | /api/products/{id}.{_format} | _api_/products/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_products_id_patch | id |
| GET | /api/pvz_prices | _api_/pvz_prices_get_collection | api_platform.symfony.main_controller::__invoke | api_pvz_prices_get_collection | ?page ?itemsPerPage ?city ?cityFias.id ?cityFias.id[] ?region |
| POST | /api/pvz_prices.{_format} | _api_/pvz_prices{._format}_post | api_platform.symfony.main_controller::__invoke | api_pvz_prices_post |  |
| DELETE | /api/pvz_prices/{id}.{_format} | _api_/pvz_prices/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_pvz_prices_id_delete | id |
| GET | /api/pvz_prices/{id}.{_format} | _api_/pvz_prices/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_pvz_prices_id_get | id |
| PATCH | /api/pvz_prices/{id}.{_format} | _api_/pvz_prices/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_pvz_prices_id_patch | id |
| PUT | /api/pvz_prices/{id}.{_format} | _api_/pvz_prices/{id}{._format}_put | api_platform.symfony.main_controller::__invoke | api_pvz_prices_id_put | id |
| GET | /api/users.{_format} | _api_/users{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_users_get_collection | ?page ?itemsPerPage |
| POST | /api/users.{_format} | _api_/users{._format}_post | api_platform.symfony.main_controller::__invoke | api_users_post |  |
| DELETE | /api/users/{id}.{_format} | _api_/users/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_users_id_delete | id |
| GET | /api/users/{id}.{_format} | _api_/users/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_users_id_get | id |
| PATCH | /api/users/{id}.{_format} | _api_/users/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_users_id_patch | id |
| GET | /api/v2/product_images.{_format} | _api_/v2/product_images{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_v2product_images_get_collection | ?page ?itemsPerPage |
| POST | /api/v2/product_images.{_format} | _api_/v2/product_images{._format}_post | api_platform.symfony.main_controller::__invoke | api_v2product_images_post |  |
| DELETE | /api/v2/product_images/{id}.{_format} | _api_/v2/product_images/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_v2product_images_id_delete | id |
| GET | /api/v2/product_images/{id}.{_format} | _api_/v2/product_images/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_v2product_images_id_get | id |
| PATCH | /api/v2/product_images/{id}.{_format} | _api_/v2/product_images/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_v2product_images_id_patch | id |
| GET | /api/v2/products.{_format} | _api_/v2/products{._format}_get_collection | api_platform.symfony.main_controller::__invoke | api_v2products_get_collection | ?page ?itemsPerPage ?q |
| POST | /api/v2/products.{_format} | _api_/v2/products{._format}_post | api_platform.symfony.main_controller::__invoke | api_v2products_post |  |
| DELETE | /api/v2/products/{id}.{_format} | _api_/v2/products/{id}{._format}_delete | api_platform.symfony.main_controller::__invoke | api_v2products_id_delete | id |
| GET | /api/v2/products/{id}.{_format} | _api_/v2/products/{id}{._format}_get | api_platform.symfony.main_controller::__invoke | api_v2products_id_get | id ?q |
| PATCH | /api/v2/products/{id}.{_format} | _api_/v2/products/{id}{._format}_patch | api_platform.symfony.main_controller::__invoke | api_v2products_id_patch | id |
| GET | /api/validation_errors/{id} | api_validation_errors | api_platform.action.not_exposed::__invoke |  |  |
| GET | /api/validation_errors/{id} | _api_validation_errors_problem | api_platform.symfony.main_controller::__invoke |  |  |
| GET | /api/validation_errors/{id} | _api_validation_errors_hydra | api_platform.symfony.main_controller::__invoke |  |  |
| GET | /api/validation_errors/{id} | _api_validation_errors_jsonapi | api_platform.symfony.main_controller::__invoke |  |  |
| GET | /api/validation_errors/{id} | _api_validation_errors_xml | api_platform.symfony.main_controller::__invoke |  |  |
| HEAD | /api/validation_errors/{id} | api_validation_errors | api_platform.action.not_exposed::__invoke |  |  |

**Source: custom_controller**

| Method | Path | Route | Controller | OpenAPI opId | Params |
|---|---|---|---|---|---|
| GET | / | app_home | App\Controller\HomeController::index |  |  |
| GET | /admin/{vueRouting} | admin_dashboard | App\Controller\Admin\DashboardController::dashboard |  |  |
| GET | /admin/login | admin_login_redirect_get | App\Controller\Admin\SecurityController::loginRedirectGet |  |  |
| POST | /admin/login | admin_login_redirect_post | App\Controller\Admin\SecurityController::loginRedirectPost |  |  |
| POST | /admin/media/cache/{size}/generate | admin_media_cache_generate_size | App\Controller\Admin\MediaAdminController::generateForSize |  |  |
| POST | /admin/session/clear-all | admin_session_clear_all | App\Controller\SessionController::clearAllSessions |  |  |
| GET | /api/admin/categories/tree | admin_api_categories_tree | App\Controller\Admin\Api\CategoriesTreeController::__invoke |  |  |
| GET | /api/admin/facets/available | admin_facets_available | App\Controller\Admin\FacetController::available |  |  |
| GET | /api/admin/facets/config | admin_facets_config_get | App\Controller\Admin\FacetConfigController::getConfig |  |  |
| PUT | /api/admin/facets/config | admin_facets_config_put | App\Controller\Admin\FacetConfigController::putConfig |  |  |
| POST | /api/admin/facets/reindex | admin_facets_reindex | App\Controller\Admin\FacetController::reindex |  |  |
| GET | /api/admin/media/jpg-list | admin_media_jpg_list | App\Controller\Admin\MediaAdminController::listJpg |  |  |
| GET | /api/admin/media/list | admin_media_list | App\Controller\Admin\MediaAdminController::listImagesInDirectory |  |  |
| DELETE | /api/admin/media/product-image/{id} | admin_media_delete_product_image | App\Controller\Admin\MediaAdminController::deleteProductImage |  |  |
| GET | /api/admin/media/product/{id}/images | admin_media_product_images | App\Controller\Admin\MediaAdminController::listProductImagesForProduct |  |  |
| POST | /api/admin/media/product/{id}/images | admin_media_attach_images | App\Controller\Admin\MediaAdminController::attachImagesToProduct |  |  |
| POST | /api/admin/media/product/{id}/images/reorder | admin_media_reorder_product_images | App\Controller\Admin\MediaAdminController::reorderProductImages |  |  |
| GET | /api/admin/media/tree | admin_media_tree | App\Controller\Admin\MediaAdminController::listDirectoryTree |  |  |
| GET | /api/admin/products/{id}/bootstrap | admin_api_product_bootstrap | App\Controller\Admin\Api\ProductBootstrapController::__invoke |  |  |
| POST | /api/admin/products/{id}/copy | admin_api_product_copy | App\Controller\Admin\Api\ProductCopyController::copyProduct |  |  |
| GET | /api/admin/products/{id}/form | admin_api_product_form_edit | App\Controller\Admin\Api\ProductFormController::formEdit |  |  |
| POST | /api/admin/products/{id}/sync | admin_api_product_sync | App\Controller\Admin\Api\ProductSyncController::__invoke |  |  |
| GET | /api/admin/products/form | admin_api_product_form_new | App\Controller\Admin\Api\ProductFormController::formNew |  |  |
| POST | /api/admin/search/reindex-products | admin_search_reindex_products | App\Controller\Admin\SearchController::reindexProducts |  |  |
| POST | /api/admin/yandex-delivery/offers/create | admin_yandex_delivery_offers_create | App\Controller\Admin\Api\YandexDeliveryController::offersCreate |  |  |
| POST | /api/admin/yandex-delivery/pickup-points/list | admin_yandex_delivery_pickup_points_list | App\Controller\Admin\Api\YandexDeliveryController::pickupPointsList |  |  |
| POST | /api/admin/yandex-delivery/pickup-points/sync | admin_yandex_delivery_pickup_points_sync | App\Controller\Admin\Api\YandexDeliveryController::pickupPointsSync |  |  |
| GET | /api/catalog/facets | catalog_facets | App\Controller\Catalog\FacetsController::__invoke |  |  |
| GET | /api/wishlist | app_api_wishlistapi_list | App\Controller\Api\WishlistApiController::list |  |  |
| GET | /api/wishlist/count | app_api_wishlistapi_count | App\Controller\Api\WishlistApiController::count |  |  |
| POST | /api/wishlist/items | app_api_wishlistapi_add | App\Controller\Api\WishlistApiController::add |  |  |
| DELETE | /api/wishlist/items/{productId} | app_api_wishlistapi_remove | App\Controller\Api\WishlistApiController::remove |  |  |
| GET | /cart | cart_page | App\Controller\Catalog\CartController::index |  |  |
| GET | /cart/claim | cart_claim | App\Controller\CartClaimController::claim |  |  |
| GET | /catalog | catalog_index | App\Controller\Catalog\IndexController::index |  |  |
| GET | /category/{slug} | catalog_category_show | App\Controller\Catalog\Category\CatalogCategoryController::show |  |  |
| GET | /category/{slug}/products | catalog_category_products | App\Controller\Catalog\Category\CatalogCategoryController::products |  |  |
| GET | /checkout | checkout_page | App\Controller\Catalog\CheckoutController::index |  |  |
| POST | /checkout | checkout_submit | App\Controller\Catalog\CheckoutController::submit |  |  |
| GET | /checkout/success | checkout_success | App\Controller\Catalog\CheckoutController::success |  |  |
| GET | /clear-session | clear_session_simple | App\Controller\SessionController::clearSimple |  |  |
| GET | /delivery | delivery_page | App\Controller\Catalog\DeliveryPageController::index |  |  |
| GET | /delivery/points | public_delivery_points | App\Controller\Api\DeliveryPublicController::points |  |  |
| GET | /delivery/price | public_delivery_price | App\Controller\Api\DeliveryPublicController::price |  |  |
| GET | /delivery/pvz-points | public_delivery_pvz_points | App\Controller\Api\DeliveryPublicController::pvzPoints |  |  |
| GET | /dostavka | catalog_dostavka | App\Controller\Catalog\InfoController::dostavka |  |  |
| GET | /login | app_login | App\Controller\SecurityController::login |  |  |
| POST | /login | app_login | App\Controller\SecurityController::login |  |  |
| GET | /logout | app_logout | App\Controller\SecurityController::logout |  |  |
| GET | /media/cache/{size}/{path} | media_image_cache | App\Controller\Media\ImageCacheController::getCached |  |  |
| GET | /media/cache/resolve/{filter}/{path} | liip_imagine_filter | Liip\ImagineBundle\Controller\ImagineController::filterAction |  |  |
| GET | /media/cache/resolve/{filter}/rc/{hash}/{path} | liip_imagine_filter_runtime | Liip\ImagineBundle\Controller\ImagineController::filterRuntimeAction |  |  |
| GET | /product/{slug} | catalog_product_show | App\Controller\Catalog\Product\ProductCatalogController::show |  |  |
| GET | /search/ | catalog_search | App\Controller\Catalog\SearchController::index |  |  |
| GET | /search/products | catalog_search_products | App\Controller\Catalog\SearchController::products |  |  |
| GET | /session/clear | session_clear | App\Controller\SessionController::clear |  |  |
| POST | /session/clear | session_clear | App\Controller\SessionController::clear |  |  |
| POST | /session/clear-key/{key} | session_clear_key | App\Controller\SessionController::clearKey |  |  |
| GET | /session/info | session_info | App\Controller\SessionController::info |  |  |
| ANY | /stimulus/test | stimulus_test | App\Controller\StimulusTestController::test |  |  |
| GET | /wishlist | catalog_wishlist | App\Controller\Catalog\WishlistController::index |  |  |

**Source: internal|debug**

| Method | Path | Route | Controller | OpenAPI opId | Params |
|---|---|---|---|---|---|
| ANY | /_components/{_live_component}/{_live_action} | ux_live_component |  |  |  |
| GET | /_debug/session | app_debug_session | App\Controller\Debug\SessionDebugController::__invoke |  |  |
| GET | /_debug/session-page | app_debug_session_page | App\Controller\Debug\SessionDebugController::page |  |  |
| POST | /_debug/session/clear | app_debug_session_clear | App\Controller\Debug\SessionDebugController::clear |  |  |
| POST | /_debug/session/destroy | app_debug_session_destroy | App\Controller\Debug\SessionDebugController::destroy |  |  |
| POST | /_debug/session/regenerate | app_debug_session_regenerate | App\Controller\Debug\SessionDebugController::regenerate |  |  |
| ANY | /_error/{code}.{_format} | _preview_error | error_controller::preview |  |  |
| ANY | /_profiler/ | _profiler_home | web_profiler.controller.profiler::homeAction |  |  |
| ANY | /_profiler/{token} | _profiler | web_profiler.controller.profiler::panelAction |  |  |
| ANY | /_profiler/{token}/exception | _profiler_exception | web_profiler.controller.exception_panel::body |  |  |
| ANY | /_profiler/{token}/exception.css | _profiler_exception_css | web_profiler.controller.exception_panel::stylesheet |  |  |
| ANY | /_profiler/{token}/router | _profiler_router | web_profiler.controller.router::panelAction |  |  |
| ANY | /_profiler/{token}/search/results | _profiler_search_results | web_profiler.controller.profiler::searchResultsAction |  |  |
| ANY | /_profiler/font/{fontName}.woff2 | _profiler_font | web_profiler.controller.profiler::fontAction |  |  |
| ANY | /_profiler/open | _profiler_open_file | web_profiler.controller.profiler::openAction |  |  |
| ANY | /_profiler/phpinfo | _profiler_phpinfo | web_profiler.controller.profiler::phpinfoAction |  |  |
| ANY | /_profiler/search | _profiler_search | web_profiler.controller.profiler::searchAction |  |  |
| ANY | /_profiler/search_bar | _profiler_search_bar | web_profiler.controller.profiler::searchBarAction |  |  |
| ANY | /_profiler/xdebug | _profiler_xdebug | web_profiler.controller.profiler::xdebugAction |  |  |
| ANY | /_wdt/{token} | _wdt | web_profiler.controller.profiler::toolbarAction |  |  |
| ANY | /_wdt/styles | _wdt_stylesheet | web_profiler.controller.profiler::toolbarStylesheetAction |  |  |

