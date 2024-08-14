<?php

class Products
{
    /**
     * @param $id
     */
    public static function Delete ($id)
    {
        $item = sql_single('SELECT * FROM '.TBL_ITEMS.' WHERE i_id='.$id);

        // Categories links
        sql_delete(TBL_LNK_CI,'ci_item='.$id);

        // Icon
        \Shop::$libs->images->Delete($item['i_icon']);

        // Presentations
        $result = sql_use('SELECT p_id FROM '.TBL_PRESENTATIONS.' WHERE p_item='.$id);
        while ($presentation = sql_fetch($result))
        {
            Presentations::Delete($presentation['p_id']);
        }
        @unlink('resources/xml/presentations/'.$id.'.xml');

        // Discounts
        Discounts::RemoveByDest(DSC_TYPE_PRODUCT,$id);

        // Comments
        sql_delete(TBL_ITEMS_COMMENTS,'item='.$id);

        // Ratings
        sql_delete(TBL_RATINGS,'item='.$id);

        // Item itself
        sql_delete(TBL_ITEMS,'i_id='.$id);
        Shop::$libs->cache->invalidate([Item::getCacheTag(), Category::getCacheTag()]);

        @unlink('images/content/items/'.$id.'.gif');
    }

    /**
     * @param $product
     * @param bool $nocache
     *
     * @return string
     */
    function GetPreview ($product, $nocache = false) // DEPRECATED
    {
        if (($product['i_preview'] == '?') || $nocache)
        {
            $query = 'SELECT * FROM '.TBL_PRESENTATIONS.' as p '.
                     'LEFT JOIN '.TBL_PRESENTATIONS_TYPES.' as t ON (p.p_type=t.t_id) '.
                     'WHERE p.p_item='.$product['i_id'].' AND p.p_enabled=1 AND t.t_enabled=1 AND p.p_preview=0 '.
                     'ORDER BY p.p_position '.
                     'LIMIT 1';
            $product['i_preview'] = ($preview = sql_single($query)) ? presentations::preview($preview) : '';
            sql_update(TBL_ITEMS,array('i_preview'=>$product['i_preview']),'i_id='.$product['i_id']);
            Shop::$libs->cache->invalidate([Item::getCacheTag()]);
        }
        return $product['i_preview'];
    }

    /**
     * @param $id
     *
     * @return array|null
     */
    public static function Get ($id)
    {
        $item = Shop::$libs->cache->get('GetItem-'.$id, function() use ($id) {
            return sql_single('SELECT * FROM '.TBL_ITEMS.' WHERE i_id='.(int)$id);
        }, [Item::getCacheTag()]);

        return $item;
    }

    /**
     * @param $product
     *
     * @return array|false
     */
    public static function GetCategories ($product)
    {
        return Shop::$libs->cache->get("productsGetCategories-".$product['i_id'], function() use ($product){
            $query = 'SELECT c.c_id ' .
                'FROM ' . TBL_LNK_CI . ' as ci ' .
                'LEFT JOIN ' . TBL_CATEGORIES . ' as c ' .
                'ON ci.ci_category=c.c_id ' .
                'WHERE ci.ci_item=' . $product['i_id'] . ' ' .
                'AND c.c_enabled=1';
            $result = sql_use($query);
            if (sql_num_rows($result)) {
                $categories = array();
                while ($category = sql_fetch_array($result)) {
                    $categories[] = (int)$category['c_id'];
                }
                return $categories;
            } else {
                return false;
            }
        }, Category::getCacheTag());
    }

    /**
     * @param $product
     *
     * @return bool
     */
    public static function IsAccessible ($product)
    {
        return ($product['i_enabled'] && Users::Access($product['i_access']));
    }

    /**
     * @param $product
     * @param int $amount
     *
     * @return bool
     */
    public static function IsAvailable ($product, $amount = 1)
    {
        return (Products::IsAccessible($product) && Products::IsExistent($product,$amount));
    }

    /**
     * @param $product
     * @param int $amount
     *
     * @return bool
     */
    public static function IsExistent ($product, $amount = 1)
    {
        $available_amount = ($product['i_static_amount'] != -1) ? $product['i_static_amount'] : $product['i_ammount'];

        return ($available_amount >= $amount);
    }

    /**
     * @param $product
     */
    function UpdatePreviewCache ($product)
    {
        Products::GetPreview($product,true);
    }

    /**
     * @param $xyz
     *
     * @return float|int
     */
    public static function XYZToVolume ($xyz)
    {
        return preg_match('/(\d+)\D+(\d+)\D+(\d+)/',$xyz,$m) ? $m[1]*$m[2]*$m[3] : 0;
    }
}
