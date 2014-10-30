<?php

/**
 * LtDbTableDataGaateway
 * @author Jianxiang Qin <TalkativeDoggy@gmail.com>
 * @license http://opensource.org/licenses/BSD-3-Clause New BSD License
 * @version svn:$Id: DbTableDataGateway.php 964 2012-08-27 04:02:32Z zhao5908@gmail.com $
 */

/**
 * LtDbTableDataGateway
 * @author Jianxiang Qin <TalkativeDoggy@gmail.com>
 * @category runtime
 * @package   Lotusphp\DB\QueryEngine
 * @subpackage TableDataGateway
 * @todo pretty join support
 */
class LtDbTableDataGateway
{
	/** @var LtConfig config handle */
	public $configHandle;
	
	/** @var LtDbHandle db handle */
	public $dbh;

	/** @var string The created field name */
	public $createdColumn;

	/** @var string The modified field name */
	public $modifiedColumn;

	/** @var string The table name */
	public $tableName;

	/** @var array The fields array */
	protected $fields;

	/** @var string The primary key */
	protected $primaryKey;
	
	/** @var array servers */
	protected $servers;

	/**
	 * Build table's field list
	 * 
	 * @return void
	 */
	protected function buildFieldList()
	{
		if (empty($this->fields))
		{
            $servers = $this->configHandle->get('db.servers');
            $group = $this->dbh->group;
            $node = $this->dbh->node;
            $role = $this->dbh->role;
            $table = $this->tableName;
            $host = key($servers[$group][$node][$role]);
            $key = md5($group . $node . $role . $table . $host . $table);
            if (!$value = $this->configHandle->get($key))
            {
                $sql = $this->dbh->sqlAdapter->showFields($this->tableName);
                $rs = $this->dbh->query($sql);
                $this->fields = $this->dbh->sqlAdapter->getFields($rs);
                foreach ($this->fields as $field)
                {
                    if ($field['primary'] == 1)
                    {
                        $this->primaryKey = $field['name'];
                        break;
                    }
                }

                $value['fields'] = $this->fields;
                $value['primaryKey'] = $this->primaryKey;
                $this->configHandle->addConfig(array($key => $value));
            }
            else
            {
                $this->fields = $value['fields'];
                $this->primaryKey = $value['primaryKey'];
            }
        }
	}

	/**
	 * A shortcut to SELECT COUNT(*) FROM table WHERE condition
	 * 
	 * @param array $args
     * @param boolean $useSlave
	 * @return integer 
	 * @example count(array('expression' => 'id < :id', 'value' => array('id' => 10)));
	 */
	public function count($args = null, $useSlave=true)
	{
		$countKey = isset($args['groupby']) ? 'DISTINCT ' . $args['groupby'] : '*';
		$selectTemplate = 'SELECT COUNT(' . $countKey . ') AS total FROM %s%s';
		$where = isset($args['where']['expression']) ? ' WHERE ' . $args['where']['expression'] : '';
		$bind = isset($args['where']['value']) ? $args['where']['value'] : array();
		$join = isset($args['join']) ? ' ' . $args['join'] : '';
		$sql = sprintf($selectTemplate, $this->tableName, $join . $where);
        $forceUseMaster = !$useSlave;
		$queryResult = $this->dbh->query($sql, $bind, $forceUseMaster);
		return $queryResult[0]['total'];
	}

	/**
	 * Delete a row by primary key
	 * 
	 * @param string $primaryKeyId 
	 * @return string 
	 * @example delete(10);
	 */
	public function delete($primaryKeyId)
	{
		$this->buildFieldList();
		$where['expression'] = $this->primaryKey . '=:' . $this->primaryKey;
		$where['value'][$this->primaryKey] = $primaryKeyId;
		return $this->deleteRows($where);
	}

	/**
	 * Delete many rows from table
	 * Please use this method carefully!
	 * 
	 * @param array $args 
	 * @return integer 
	 * @example deleteRows(array('expression' => "id > :id", 'value' => array('id' => 2)));
	 */
	public function deleteRows($args = null)
	{
		$deleteTemplate = 'DELETE FROM %s%s';
		$where = isset($args['expression']) ? ' WHERE ' . $args['expression'] : '';
		$bind = isset($args['value']) ? $args['value'] : array();
		$sql = sprintf($deleteTemplate, $this->tableName, $where);
		return $this->dbh->query($sql, $bind);
	}

	/**
	 * Fetch one row from table by primary key
	 * 
	 * @param string $primaryKeyId 
	 * @param array $args 
	 * @param boolean $useSlave 
	 * @return array 
	 * @example fetch(10)
	 */
	public function fetch($primaryKeyId, $args = null, $useSlave = true)
	{
		$this->buildFieldList();
		$fetchRowsArgs['where']['expression'] = $this->tableName . '.' . $this->primaryKey . '=:' . $this->primaryKey;
		$fetchRowsArgs['where']['value'][$this->primaryKey] = $primaryKeyId;
		$fetchRowsArgs['fields'] = isset($args['fields']) ? $args['fields'] : null;
		$fetchRowsArgs['join'] = isset($args['join']) ? $args['join'] : null;
		$fetchResult = $this->fetchRows($fetchRowsArgs, $useSlave);
		return $fetchResult ? $fetchResult[0] : $fetchResult;
	}

	/**
	 * Fetch many rows from table
	 * 
	 * @param array $args 
	 * @param boolean $useSlave 
	 * @return array 
	 * @example fetchRows(array('where' => array('expression' => "id > :id", 'value' => array('id' => 2))));
	 */
	public function fetchRows($args = null, $useSlave = true)
	{
 		$this->buildFieldList();
		$selectTemplate = 'SELECT %s FROM %s%s';
		$fields = isset($args['fields']) ? $args['fields'] : '*';
		$where = isset($args['where']['expression']) ? ' WHERE ' . $args['where']['expression'] : '';
		$bind = isset($args['where']['value']) ? $args['where']['value'] : array();
		$join = isset($args['join']) ? ' ' . $args['join'] : '';
		$orderby = isset($args['orderby']) ? ' ORDER BY ' . $args['orderby'] : '';
		$groupby = isset($args['groupby']) ? ' GROUP BY ' . $args['groupby'] : '';
		$table = isset($args['table']) ? $args['table'] : $this->tableName;
		$sql = sprintf($selectTemplate, $fields, $table, $join . $where . $groupby . $orderby);
		if (isset($args['limit']))
		{
			$offset = isset($args['offset']) ? $args['offset'] : 0;
			$sql = $sql . ' ' . $this->dbh->sqlAdapter->limit($args['limit'], $offset);
		}
        $forceUseMaster = !$useSlave;
		return $this->dbh->query($sql, $bind, $forceUseMaster);
	}

	/**
	 * Insert one row into table, then return the inserted row's pk
	 * 
	 * @param array $args 
	 * @return string 
	 * @example insert(array('name' => 'lily', 'age' => '12'));
	 */
	public function insert($args = null)
	{
		$this->buildFieldList();
		$insertTemplate = 'INSERT INTO %s (%s) VALUES (%s)';
		$fields = array();
		$placeholders = array();
        $values = array();
		foreach ($args as $field => $value)
		{
			if (isset($this->fields[$field]))
			{
				$fields[] = $field;
				$placeholders[] = ":$field";
				$values[$field] = $value;
			}
		}
		if (isset($this->fields[$this->createdColumn]) && !isset($args[$this->createdColumn]))
		{
			$fields[] = $this->createdColumn;
			$placeholders[] = ':' . $this->createdColumn;
			$values[$this->createdColumn] = time();
		}
		if (isset($this->fields[$this->modifiedColumn]) && !isset($args[$this->modifiedColumn]))
		{
			$fields[] = $this->modifiedColumn;
			$placeholders[] = ':' . $this->modifiedColumn;
			$values[$this->modifiedColumn] = time();
		}
		$sql = sprintf($insertTemplate, $this->tableName, implode(",", $fields), implode(",", $placeholders));
		$bind = $values;
		$queryResult = $this->dbh->query($sql, $bind);
		return isset($args[$this->primaryKey]) ? $args[$this->primaryKey] : $queryResult;
	}

	/**
	 * Update one row  by primary key
	 * 
	 * @param string $primaryKeyId 
	 * @param array $args 
	 * @return integer 
	 * @example update(1, array('name' => 'lily', 'age' => '18'));
	 */
	public function update($primaryKeyId, $args = null)
	{
		$this->buildFieldList();
		$where['expression'] = $this->primaryKey . '=:' . $this->primaryKey;
		$where['value'][$this->primaryKey] = $primaryKeyId;
		return $this->updateRows($where, $args);
	}

	/**
	 * Update manay rows
	 * Please use this method carefully!
	 * 
	 * @param array $where 
	 * @param array $args 
	 * @return integer 
	 * @example updateRows(array('expression' => "id > :id", 'value' => array('id' => 2)), array('name' => 'kiwi', 'age' => '1'));
	 */
	public function updateRows($where, $args = null)
	{
		$this->buildFieldList();
		$updateTemplate = 'UPDATE %s SET %s%s';
		$fields = array();
		$bindParameters = array();
		$placeholderStyle = isset($where['value']) && array_key_exists(0, $where['value']) ? 'questionMark' : 'named';
		foreach ($args as $field => $value)
		{
			if (isset($this->fields[$field]))
			{
				if ($args[$field] instanceof LtDbSqlExpression)
				{
					$fields[] = "$field=" . $args[$field]->__toString();
				}
				else
				{
					if ('named' == $placeholderStyle)
					{
						$fields[] = "$field=:$field";
						$bindParameters[$field] = $args[$field];
					}
					else
					{
						$fields[] = "$field=?";
						$bindParameters[] = $args[$field];
					}
				}
			}
		}
		if (isset($this->fields[$this->modifiedColumn]) && !isset($args[$this->modifiedColumn]))
		{
			if ('named' == $placeholderStyle)
			{
				$fields[] = $this->modifiedColumn . '=:' . $this->modifiedColumn;
				$bindParameters[$this->modifiedColumn] = time();
			}
			else
			{
				$fields[] = $this->modifiedColumn . '=?';
				$bindParameters[] = time();
			}
		}
		$whereCause = isset($where['expression']) ? ' WHERE ' . $where['expression'] : '';
		$bind = isset($where['value']) ? array_merge($bindParameters, $where['value']) : $bindParameters;
		$sql = sprintf($updateTemplate, $this->tableName, implode(",", $fields), $whereCause);
		return $this->dbh->query($sql, $bind);
	}

}
