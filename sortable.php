<?php

class SortableBehavior extends ModelBehavior {

    private $_defaults = array(
        'sort_field' => 'sort',
        'group_fields' => array(),
        'recursive' => -1,
    );

    public function setup(&$Model, $settings) {
        $this->settings[$Model->alias] = array_merge($this->_defaults, (array) $settings);
    }

    /**
     * Move element to the top of the list
     *
     * @param AppModel $Model Model instance
     * @param mixed $id The ID of the record to move
     * @return boolean TRUE if moved, FALSE on no move available
     */
    public function moveToTop(&$Model, $id = null) {
        if (!$id) {
            return false;
        }
        extract($this->settings[$Model->alias]);
        $current = $Model->find('first', array(
            'conditions' => array($Model->primaryKey => $id),
            'recursive' => $recursive,
        ));
        $min_sort = $this->getMinSort($Model, $current);
        $conditions = array(
            $Model->alias . '.' . $sort_field . ' >=' => $min_sort,
        );
        if (!empty($group_fields)) {
            foreach ($group_fields as $group_field) {
                $conditions[$Model->alias . '.' . $group_field] = $current[$Model->alias][$group_field];
            }
        }
        $Model->updateAll(array($Model->alias . '.' . $sort_field => $Model->alias . '.' . $sort_field . ' + 1'), $conditions);
        $Model->id = $current[$Model->alias][$Model->primaryKey];
        $Model->saveField($sort_field, $min_sort);
        return true;
    }
    
    /**
     * Move element up the list
     *
     * @param AppModel $Model Model instance
     * @param mixed $id The ID of the record to move
     * @return boolean TRUE if moved, FALSE on no move available
     */
    public function moveUp(&$Model, $id = null, $number = 1) {
        if (!$id) {
            return false;
        }
        extract($this->settings[$Model->alias]);
        $current = $Model->find('first', array(
            'conditions' => array($Model->primaryKey => $id),
            'recursive' => $recursive,
        ));
        $options = array(
            'conditions' => array(
                $Model->alias . '.' . $sort_field . ' <' => $current[$Model->alias][$sort_field],
            ),
            'order' => array($Model->alias . '.' . $sort_field => 'DESC'),
            'recursive' => $recursive,
        );
        if (!empty($group_fields)) {
            foreach ($group_fields as $group_field) {
                $options['conditions'][$Model->alias . '.' . $group_field] = $current[$Model->alias][$group_field];
            }
        }
        $previous = $Model->find('first', $options);
        if (!empty($previous)) {
            $Model->id = $current[$Model->alias][$Model->primaryKey];
            $Model->saveField($sort_field, $previous[$Model->alias][$sort_field]);
            $Model->id = $previous[$Model->alias][$Model->primaryKey];
            $Model->saveField($sort_field, $current[$Model->alias][$sort_field]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Move element down the list
     *
     * @param AppModel $Model Model instance
     * @param mixed $id The ID of the record to move
     * @return boolean TRUE if moved, FALSE on no move available
     */
    public function moveDown(&$Model, $id = null, $number = 1) {
        if (!$id) {
            return false;
        }
        extract($this->settings[$Model->alias]);
        $current = $Model->find('first', array(
            'conditions' => array($Model->primaryKey => $id),
            'recursive' => $recursive,
        ));
        $options = array(
            'conditions' => array(
                $Model->alias . '.' . $sort_field . ' >' => $current[$Model->alias][$sort_field],
            ),
            'order' => array($Model->alias . '.' . $sort_field => 'ASC'),
            'recursive' => $recursive,
        );
        if (!empty($group_fields)) {
            foreach ($group_fields as $group_field) {
                $options['conditions'][$Model->alias . '.' . $group_field] = $current[$Model->alias][$group_field];
            }
        }
        $next = $Model->find('first', $options);
        if (!empty($next)) {
            $Model->id = $current[$Model->alias][$Model->primaryKey];
            $Model->saveField($sort_field, $next[$Model->alias][$sort_field]);
            $Model->id = $next[$Model->alias][$Model->primaryKey];
            $Model->saveField($sort_field, $current[$Model->alias][$sort_field]);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Move element to the end of the list
     *
     * @param AppModel $Model Model instance
     * @param mixed $id The ID of the record to move
     * @return boolean TRUE if moved, FALSE on no move available
     */
    public function moveToEnd(&$Model, $id = null) {
        if (!$id) {
            return false;
        }
        extract($this->settings[$Model->alias]);
        $current = $Model->find('first', array(
            'conditions' => array($Model->primaryKey => $id),
            'recursive' => $recursive,
        ));
        $max_sort = $this->getMaxSort($Model, $current);
        $Model->id = $current[$Model->alias][$Model->primaryKey];
        $Model->saveField($sort_field, $max_sort);
        return true;
    }

    /**
     * Place new element at the end
     *
     * @param AppModel $Model Model instance
     */
    public function beforeSave(&$Model) {
        extract($this->settings[$Model->alias]);
        if (empty($Model->data[$Model->alias][$Model->primaryKey])) {
            $Model->data[$Model->alias][$sort_field] = $this->getMaxSort($Model);
        }
    }

    /**
     * Get maximum sort index
     * 
     * @param AppModel $Model Model instance
     * @param mixed $current_record Current record for comparison
     * @return int Maximum sort index
     */
    public function getMaxSort(&$Model, $current_record = null) {
        extract($this->settings[$Model->alias]);
        $options = array(
            'fields' => 'MAX(' . $Model->alias . '.' . $sort_field . ') + 1 AS max_sort',
            'conditions' => array(),
        );
        if (!empty($group_fields)) {
            foreach ($group_fields as $group_field) {
                $options['conditions'][$Model->alias . '.' . $group_field] = 
                        (!empty($current_record) ? $current_record[$Model->alias][$group_field] : $Model->data[$Model->alias][$group_field]);
            }
        }
        $result = $Model->find('first', $options);
        return intval($result[0]['max_sort']);
    }
    
    /**
     * Get minimum sort index
     * 
     * @param AppModel $Model Model instance
     * @param mixed $current_record Current record for comparison
     * @return int Minimum sort index
     */
    public function getMinSort(&$Model, $current_record = null) {
        extract($this->settings[$Model->alias]);
        $options = array(
            'fields' => 'MIN(' . $Model->alias . '.' . $sort_field . ') AS min_sort'
        );
        if (!empty($group_fields)) {
            foreach ($group_fields as $group_field) {
                $options['conditions'][$Model->alias . '.' . $group_field] = 
                        (!empty($current_record) ? $current_record[$Model->alias][$group_field] : $Model->data[$Model->alias][$group_field]);
            }
        }
        $result = $Model->find('first', $options);
        return intval($result[0]['min_sort']);
    }
    
    /**
     * Resort entire table by given field or by primary key
     *
     * @param AppModel $Model Model instance
     * @param mixed $sort_by Field(s) to sort by
     * @param mixed $group Sort given group i.e. array('Model.group_field' => 'group_id')
     * @return boolean TRUE
     */
    public function sort(&$Model, $sort_by = null, $group = array()) {
        extract($this->settings[$Model->alias]);
        
        if (empty($sort_by)) {
            $sort_by = $Model->alias . '.' . $Model->primaryKey;
        }
        
        $conditions = array();
        
        $fields = array(
            $Model->alias . '.' . $Model->primaryKey,
        );
        if (!in_array($Model->alias . '.' . $Model->primaryKey, $fields)) {
            array_push($fields, $Model->alias . '.' . $Model->primaryKey);
        }
        
        if (!empty($group)) {
            $conditions = $group;
            foreach ($group as $group_field => $value) {
                array_push($fields, $Model->alias . '.' . $group_field);
            }
        }
        
        $records = $Model->find('all', array(
            'fields' => $fields,
            'conditions' => $conditions,
            'order' => $sort_by,
            'recursive' => $recursive,
        ));
        
        $counter = 0;
        foreach ($records as $record) {
            $counter++;
            $Model->id = $record[$Model->alias][$Model->primaryKey];
            $Model->saveField($sort_field, $counter);
        }
        
        return true;
    }

}

?>