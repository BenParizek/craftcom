<?php

namespace craftcom\controllers\id;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\Json;
use craftcom\controllers\api\BaseApiController;
use craftcom\Module;
use craftcom\plugins\Plugin;
use League\OAuth2\Client\Token\AccessToken;
use yii\web\Response;
use craft\db\Query;
use League\OAuth2\Client\Provider\Github;


/**
 * Class CraftIdController
 *
 * @package craftcom\controllers\id
 */
class CraftIdController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * Handles /v1/craft-id requests.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        // Current user
        $currentUserId = Craft::$app->getRequest()->getParam('userId');
        $currentUser = Craft::$app->getUsers()->getUserById($currentUserId);

        // Apps
        $apps = Module::getInstance()->getOauth()->getApps();

        // Plugins
        $plugins = [];
        $pluginElements = Plugin::find()->developerId($currentUser->id)->status(null)->all();

        foreach ($pluginElements as $pluginElement) {
            $plugins[] = $this->pluginTransformer($pluginElement);
        }

        // Craft licenses
        $craftLicenses = [];
        $craftLicenseEntries = Entry::find()->section('licenses')->type('craftLicense')->authorId($currentUser->id)->all();

        foreach ($craftLicenseEntries as $craftLicenseEntry) {
            $craftLicense = $craftLicenseEntry->toArray();

            $plugin = null;

            if ($craftLicenseEntry->plugin) {
                $plugin = $craftLicenseEntry->plugin->toArray();
            }

            $craftLicense['plugin'] = $plugin;
            $craftLicense['author'] = $craftLicenseEntry->getAuthor()->toArray();
            $craftLicense['type'] = $craftLicenseEntry->getType()->handle;
            $craftLicenses[] = $craftLicense;
        }

        // Plugin licenses
        $pluginLicenses = [];
        $pluginLicenseEntries = Entry::find()->section('licenses')->type('pluginLicense')->authorId($currentUser->id)->all();

        foreach ($pluginLicenseEntries as $pluginLicenseEntry) {
            $pluginLicense = $pluginLicenseEntry->toArray();
            $plugin = $pluginLicenseEntry->plugin;
            $pluginLicense['plugin'] = $plugin->toArray();
            $craftLicense = $pluginLicenseEntry->craftLicense->one();

            if ($craftLicense) {
                $pluginLicense['craftLicense'] = $craftLicense->toArray();
            } else {
                $pluginLicense['craftLicense'] = null;
            }
            $pluginLicense['type'] = $pluginLicenseEntry->getType()->handle;
            $pluginLicense['author'] = $pluginLicenseEntry->getAuthor()->toArray();

            $pluginLicenses[] = $pluginLicense;
        }

        // Customers
        $customers = [];

        foreach ($pluginElements as $pluginElement) {
            $entries = Entry::find()->section('licenses')->relatedTo($pluginElement)->all();

            foreach ($entries as $entry) {
                $found = false;

                foreach ($customers as $c) {
                    if ($c['id'] == $entry->getAuthor()->id) {
                        $found = true;
                    }
                }

                if (!$found) {
                    $customer = [
                        'id' => $entry->getAuthor()->id,
                        'email' => $entry->getAuthor()->email,
                        'username' => $entry->getAuthor()->username,
                        'fullName' => $entry->getAuthor()->fullName
                    ];

                    $customers[] = $customer;
                }
            }
        }

        // Categories
        $categories = [];
        $categoryElements = Category::find()->group('pluginCategories')->all();

        foreach ($categoryElements as $categoryElement) {
            $categories[] = [
                'id' => $categoryElement->id,
                'title' => $categoryElement->title,
            ];
        }

        // Data
        $data = [
            'currentUser' => [
                'id' => $currentUser->id,
                'email' => $currentUser->email,
                'firstName' => $currentUser->firstName,
                'lastName' => $currentUser->lastName,
                'developerName' => $currentUser->developerName,
                'developerUrl' => $currentUser->developerUrl,
                'location' => $currentUser->location,
                'enablePluginDeveloperFeatures' => ($currentUser->isInGroup('developers') ? true : false),
                'enableShowcaseFeatures' => ($currentUser->enableShowcaseFeatures == 1 ? true : false),
                'businessName' => $currentUser->businessName,
                'businessVatId' => $currentUser->businessVatId,
                'businessAddressLine1' => $currentUser->businessAddressLine1,
                'businessAddressLine2' => $currentUser->businessAddressLine2,
                'businessCity' => $currentUser->businessCity,
                'businessState' => $currentUser->businessState,
                'businessZipCode' => $currentUser->businessZipCode,
                'businessCountry' => $currentUser->businessCountry,
                'groups' => $currentUser->getGroups(),
                'photoId' => ($currentUser->getPhoto() ? $currentUser->getPhoto()->getId() : null),
                // 'photoUrl' => ($currentUser->getPhoto() ? $currentUser->getPhoto()->getUrl() : null),
                'photoUrl' => $currentUser->getThumbUrl(200),
            ],
            'apps' => $apps,
            'plugins' => $plugins,
            'craftLicenses' => $craftLicenses,
            'pluginLicenses' => $pluginLicenses,
            'customers' => $customers,
            'payouts' => $this->_getPayouts(),
            'payoutsScheduled' => $this->_getScheduledPayouts(),
            'payments' => $this->_getPayments(),
            'categories' => $categories,
        ];

        return $this->asJson($data);
    }

    // Private Methods
    // =========================================================================

    /**
     * @return array
     */
    private function _getPayouts(): array
    {
        return [
            [
                'id' => 1,
                'amount' => 99.00,
                'date' => '1 year ago',
                'bank' => [
                    'name' => 'BNP Parisbas',
                    'accountNumber' => '2345678923456783456',
                ]
            ],
            [
                'id' => 2,
                'amount' => 99.00,
                'date' => '1 year ago',
                'bank' => [
                    'name' => 'BNP Parisbas',
                    'accountNumber' => '2345678923456783456',
                ]
            ],
            [
                'id' => 3,
                'amount' => 298.00,
                'date' => '1 year ago',
                'bank' => [
                    'name' => 'BNP Parisbas',
                    'accountNumber' => '2345678923456783456',
                ]
            ],
        ];
    }

    /**
     * @return array
     */
    private function _getScheduledPayouts(): array
    {
        return [
            [
                'id' => 8,
                'amount' => 116.00,
                'date' => 'Tomorrow',
            ],
        ];
    }

    /**
     * @return array
     */
    private function _getPayments(): array
    {
        return [
            [
                'items' => [['id' => 6, 'name' => 'Analytics']],
                'amount' => 99.00,
                'customer' => [
                    'id' => 1,
                    'name' => 'Benjamin David',
                    'email' => 'ben@pixelandtonic.com',
                ],
                'date' => '3 days ago',
            ],
            [
                'items' => [['id' => 6, 'name' => 'Analytics']],
                'amount' => 99.00,
                'customer' => [
                    'id' => 15,
                    'name' => 'Andrew Welsh',
                    'email' => 'andrew@nystudio107.com',
                ],
                'date' => '1 year ago',
            ],
            [
                'items' => [['id' => 7, 'name' => 'Videos']],
                'amount' => 99.00,
                'customer' => [
                    'id' => 15,
                    'name' => 'Andrew Welsh',
                    'email' => 'andrew@nystudio107.com',
                ],
                'date' => '1 year ago',
            ],
            [
                'items' => [['id' => 6, 'name' => 'Analytics'], ['id' => 7, 'name' => 'Videos']],
                'amount' => 298.00,
                'customer' => [
                    'id' => 15,
                    'name' => 'Andrew Welsh',
                    'email' => 'andrew@nystudio107.com',
                ],
                'date' => '1 year ago',
            ],
        ];
    }
}
