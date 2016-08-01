<?php
/**
 * 2011-2015 Roman Prokofyev
 *
 * NOTICE OF LICENSE
 *
 *  @author    Roman Prokofyev
 *  @copyright 2011-2015 Roman Prokofyev
 *  @license   MIT
 */

class YMMappings
{
    private static $TABLE = 'yamarket_category_mappings';

    public static function getAll()
    {
        $sql = 'SELECT id, ym_category, id_category, category
                FROM ' . _DB_PREFIX_ . self::$TABLE;

        return Db::getInstance()->executeS($sql);
    }

    public static function getAllDict()
    {
        $mappings = array();
        $result = self::getAll();
        foreach ($result as $mapping) {
            $mappings[$mapping['id_category']] = $mapping['ym_category'];
        }
        return $mappings;
    }

    public static function add($binding_category, $ym_category)
    {
        $categories = array();
        foreach (self::getCategoriesWithParents() as $mapping) {
            $categories[$mapping['id_category']] = $mapping['name'];
        }

        Db::getInstance()->insert(
            self::$TABLE,
            array(
                'ym_category' => $ym_category,
                'id_category' => (int)$binding_category,
                'category' => $categories[$binding_category]
            )
        );
        Db::getInstance()->Insert_ID();
    }

    public static function remove($id)
    {
        $result = Db::getInstance()->delete(self::$TABLE, 'id = ' . (int)$id);
        return $result;
    }

    public static function installDb()
    {
        return (Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::$TABLE . '` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `id_category` INT UNSIGNED NOT NULL,
            `category` TEXT NOT NULL,
            `ym_category` TEXT NOT NULL
        ) ENGINE = ' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8 COLLATE utf8_general_ci;'));
    }

    public static function uninstallDb()
    {
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . self::$TABLE . '`');
        return true;
    }

    public static function getCategories()
    {
        $res = Category::getCategories((int)Configuration::get('PS_LANG_DEFAULT'), true, false);
        return array_filter($res, array("YMMappings", "isValid"));
    }

    public static function getCategoriesWithParents()
    {
        $res = Category::getCategories((int)Configuration::get('PS_LANG_DEFAULT'), true, false);
        $categories_map = array();
        foreach ($res as $category) {
            $categories_map[$category['id_category']] = $category;
        }
        $new_categories = array();
        foreach ($res as $category) {
            while($category['id_parent'] > 1)
            {
                $category['name'] = $categories_map[$category['id_parent']]['name'].'/'.$category['name'];
                $category['id_parent'] = $categories_map[$category['id_parent']]['id_parent'];
            }
            $new_categories[] = $category;
        }

        return array_filter($new_categories, array("YMMappings", "isValid"));
    }

    private static function isValid($category)
    {
        return $category['id_category'] > 1;
    }
}
