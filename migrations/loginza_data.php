<?php
/**
 * Created by PhpStorm.
 * User: Georg
 * Date: 14.10.2017
 * Time: 00:03
 */

class loginza_data extends phpbb\db\migration\migration
{
    static public function depends_on()
    {
        return ['phpbb\db\migration\data\v320\v320'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'users' => [
                    'loginza_identity' => [
                        'VCHAR:255', ''
                    ],
                    'loginza_provider' => [
                        'VCHAR:255', ''
                    ],
                ]
            ],
            'add_index' => [
                $this->table_prefix . 'users' => [
                    'loginza_identity' => [
                        'loginza_identity'
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'users' => [
                    'loginza_identity',
                    'loginza_provider'
                ],
            ],
        ];
    }
}
