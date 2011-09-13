<?php

class YmarketSection extends ActiveRecordModel
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}


	public function tableName()
	{
		return 'ymarket_sections';
	}


	public function rules()
	{
		return array(
			array('url', 'required'),
			array('name, yandex_name', 'length', 'max' => 100),
            array('name', 'unique', 'className' => get_class($this), 'attributeName' => 'name'),
            array('url', 'unique', 'className' => get_class($this), 'attributeName' => 'url'),
            array('yandex_name', 'unique', 'className' => get_class($this), 'attributeName' => 'yandex_name'),
			array('url', 'length', 'max' => 250),
            array(
                'url',
                'match',
                'pattern' => '|http\:\/\/market\.yandex\.ru\/catalogmodels\.xml\?CAT_ID=[0-9]+.*?|',
                'message' => 'Формат: http://market.yandex.ru/catalogmodels.xml?CAT_ID=.....'
            ),
			array('id, name, yandex_name, url, breadcrumbs, date_create', 'safe', 'on' => 'search'),
		);
	}


	public function relations()
	{
		return array(
			'rels'   => array(self::HAS_MANY, 'YmarketSectionRel', 'section_id'),
            'brands' => array(
                self::HAS_MANY,
                'YmarketBrand',
                'object_id',
                'through'   => 'rels',
                'condition' => "object_type = '" . YmarketSectionRel::OBJECT_TYPE_BRAND . "'"
            )
		);
	}


	public function search()
	{
		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id, true);
		$criteria->compare('name', $this->name, true);
		$criteria->compare('yandex_name', $this->yandex_name, true);
		$criteria->compare('url', $this->url, true);
		$criteria->compare('breadcrumbs', $this->breadcrumbs, true);
		$criteria->compare('date_create', $this->date_create, true);

        $page_size = 10;
        if (isset(Yii::app()->session[get_class($this) . "PerPage"]))
        {
            $page_size = Yii::app()->session[get_class($this) . "PerPage"];
        }

		return new CActiveDataProvider(get_class($this), array(
			'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $page_size,
            ),
		));
	}


    public function parseAndUpdateAttributes()
    {
        $content = YmarketIP::model()->doRequest($this->url);
        //$content = file_get_contents("/var/www/SectionContent.html");
        $content = html_entity_decode($content);


        preg_match('|<h1>(.*?)</h1>|', $content, $yandex_name);

        if (!isset($yandex_name[1]))
        {
            Yii::log(
                'Ymarket:: не могу спарсить название раздела ' . $this->url,
                'error',
                'ymarket'
            );
            return;
        }

        $this->yandex_name = trim($yandex_name[1]);

        preg_match('|<a style="[^"]+" href="([^"]+)">Посмотреть все модели</a>|', $content, $all_models_url);
        if (isset($all_models_url[1]))
        {
            $this->all_models_url = trim($all_models_url[1]);
        }
        else
        {
            Yii::log(
                'Ymarket:: не могу спарсить ссылку на все модели' . $this->url,
                'warning',
                'ymarket'
            );
        }

        preg_match('|<div class="b-breadcrumbs">(.*?)</div>|', $content, $breadcrumbs);
        if (isset($breadcrumbs[1]))
        {
            $this->breadcrumbs = trim($breadcrumbs[1]);
        }
        else
        {
            Yii::log(
                'Ymarket:: не могу спарсить хлебные крошки ' . $this->url,
                'warning',
                'ymarket'
            );
        }

        preg_match('|<a href="([^"]+)" class="black">все производители[^<]+</a>|', $content, $brands_url);
        if (isset($brands_url[1]))
        {
            $this->brands_url = trim($brands_url[1]);
        }
        else
        {
            Yii::log(
                'Ymarket:: не могу спарсить ссылку на всех производителей ' . $this->url,
                'warning',
                'ymarket'
            );
        }

        $this->date_update = new CDbExpression('NOW()');
        $this->save();
    }


    public function parseAndUpdateBrands()
    {
        if (!$this->brands_url)
        {
            return;
        }

        $content = file_get_contents(YmarketModule::YANDEX_MARKET_WEB_URL . $this->brands_url);
        //$content = file_get_contents("/var/www/SectionBrands.html");
        $content = html_entity_decode($content);

        preg_match_all('|<ul class="list vendor">(.*?)</ul>|', $content, $uls);
        if (!isset($uls[1]))
        {
            return;
        }

        foreach ($uls[1] as $ul)
        {
            preg_match('|<a href=".*?">(.*?)</a>|', $ul, $brand_name);
            if (isset($brand_name[1]))
            {
                $brand_name = trim($brand_name[1]);

                $brand = YmarketBrand::model()->findByAttributes(array('name' => $brand_name));
                if (!$brand)
                {
                    $brand = new YmarketBrand;
                    $brand->name = $brand_name;
                    $brand->save();
                }

                $attributes = array(
                    'object_type' => YmarketSectionRel::OBJECT_TYPE_BRAND,
                    'section_id'  => $this->id,
                    'object_id'   => $brand->id
                );

                $section_rel = YmarketSectionRel::model()->findByAttributes($attributes);
                if (!$section_rel)
                {
                    $section_rel = new YmarketSectionRel();
                    $section_rel->attributes = $attributes;
                    $section_rel->save();
                }
            }
        }

        $this->date_brand_update = new CDbExpression('NOW()');
        $this->save();
    }
}