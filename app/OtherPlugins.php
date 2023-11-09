<?php 

namespace BeycanPress\BinancePay;

class OtherPlugins
{   
    /**
     * @var string
     */
    private $apiUrl = 'https://beycanpress.com/wp-json/bp-api/';
    
    /**
     * Class construct
     * @return void
     */
    public function __construct(string $pluginFile)
    {
        if (!isset($GLOBALS['admin_page_hooks']['beycanpress-plugins'])) {
            add_action('admin_menu', function() use ($pluginFile) {
                add_menu_page( 
                    esc_html__('BeycanPress Plugins', 'binance_pay_gateway'),
                    esc_html__('BeycanPress Plugins', 'binance_pay_gateway'),
                    'manage_options', 
                    'beycanpress-plugins',
                    [$this, 'page'],
                    plugin_dir_url($pluginFile) . 'assets/images/beycanpress.png',
                );
            });
            $GLOBALS['admin_page_hooks']['beycanpress-plugins'] = true;
        }
    }

    /**
     * @return void
     */
    public function page() : void
    {
        $res = file_get_contents($this->apiUrl . 'general-products');
        $res = json_decode(str_replace(['<p>', '</p>'], '', $res));
        $plugins = $res->data->products;
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html__('BeycanPress Plugins', 'binance_pay_gateway'); ?>
            </h1>
            <hr class="wp-header-end">
            <br>
            <div class="wrapper">
                <div class="box box-33">
                    <div class="postbox">
                        <div class="activity-block" style="padding: 20px; box-sizing: border-box; margin:0">
                            <ul class="product-list">
                                <?php if (isset($plugins)) :
                                    foreach ($plugins as $product) : ?>
                                        <li>
                                            <a href="<?php echo esc_url($product->permalink) ?>" target="_blank">
                                                <img src="<?php echo esc_url($product->image) ?>" alt="<?php echo esc_attr($product->title) ?>">
                                                <span><?php echo esc_html($product->title) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach;
                                else :
                                    echo esc_html__('No product found!');
                                endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .product-list {
                display: flex;
                flex-wrap: wrap;
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .product-list li {
                width: 20%;
                padding: 10px;
                box-sizing: border-box;
            }

            .product-list li a {
                display: block;
                text-align: center;
                text-decoration: none;
                color: #333;
            }

            .product-list li a img {
                width: 100%;
                height: auto;
                border-radius: 5px;
                margin-bottom: 10px;
            }

            @media screen and (max-width: 768px) {
                .product-list li {
                    width: 50%;
                }
                
            }

            @media screen and (max-width: 400px) {
                .product-list li {
                    width: 100%;
                }
                
            }
        </style>
        <?php
    }
}