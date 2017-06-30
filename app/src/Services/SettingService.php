<?php
namespace App\Services;

class SettingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }
    public function get($name, $default = null)
    {
        $names           = is_array($name) ? $name : [$name];
        $selectStatement = $this->db->select()
            ->from('settings')
            ->whereIn('name', $names);

        $stmt = $selectStatement->execute();
        $rows = [];
        while ($data = $stmt->fetch())
        {
            if (is_null($data['expires']))
            {
                $rows[$data['name']] = $data['value'];
            }
            elseif (time() >= strtotime($data['expires']))
            {
                $deleteStatement = $this->db->delete()
                    ->from('settings')
                    ->where('name', '=', $name);

                $affectedRows = $deleteStatement->execute();

                $rows[$data['name']] = $default;
            }
            else
            {
                $rows[$data['name']] = $data['value'];
            }
        }
        foreach ($names as $name)
        {
            if (!isset($rows[$name]))
            {
                $rows[$name] = $default;
            }
        }

        if (count($names) > 1)
        {
            return $rows;
        }
        else
        {
            $rows = array_values($rows);
            return $rows[0];
        }
    }
    public function set($name, $value = null, $expires = null)
    {
        $rows = is_array($name) ? $name : [$name => $value];
        foreach ($rows as $name => $value)
        {
             
            try
            {
                $tm              = date("Y-m-d H:i:s", time());
                $expires         = is_null($expires) ? $expires : date("Y-m-d H:i:s", time() + $expires);
                $insertStatement = $this->db->insert(array('name', 'value', 'created', 'updated', 'expires'))
                    ->into('settings')
                    ->values(array($name, $value, $tm, $tm, $expires));
                $insertId = $insertStatement->execute(false);
            }
            catch (\Exception $e)
            {
                if ($e->getCode() == 23000)
                {
                    $updateStatement = $this->db->update(
                        array(
                            'value'   => $value,
                            'updated' => $tm,
                        ))
                        ->table('settings')
                        ->where('name', '=', $name);

                    $affectedRows = $updateStatement->execute();
                }
                else
                {
                    throw $e;
                }
            }
        }
        return $rows;
    }
}
