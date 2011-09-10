<?php

class City extends ActiveRecordModel
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}


	public function tableName()
	{
		return 'cities';
	}


	public function rules()
	{
		return array(
			array('name', 'length', 'max' => 100, 'min' => 3),
            array('name', 'unique', 'attributeName' => 'name', 'className' => 'City'),
			array('id, name', 'safe', 'on' => 'search')
		);
	}


	public function relations()
	{
		return array();
	}


	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id,true);
		$criteria->compare('name',$this->name,true);

        $page_size = 10;
        if (isset(Yii::app()->session[get_class($this) . "PerPage"]))
        {
            $page_size = Yii::app()->session[get_class($this) . "PerPage"];
        }

        $this->addLangCondition($criteria);

		return new CActiveDataProvider(get_class($this), array(
			'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $page_size,
            ),
		));
	}


    public function findOrCreate($name)
    {
        $city = $this->findByAttributes(array("name" => trim($name)));
        if ($city)
        {
            return $city->id;
        }
        else
        {
            $city = new City();
            $city->name = $name;
            $city->save();
            return $city->id;
        }
    }
}