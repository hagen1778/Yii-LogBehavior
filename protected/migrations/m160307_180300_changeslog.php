<?php

class m160307_180300_changeslog extends CDbMigration
{
	public function up()
	{
		$this->createTable('changeslog', [
			'id' => 'pk',
			'title' => 'string NOT NULL',
			'old_message' => 'text',
			'new_message' => 'text',
			'ip' => 'INT',
			'entity_id' => 'INT',
			'entity_key' => 'string NOT NULL',
			'date' => 'datetime'
		]);
	}

	public function down()
	{
		$this->dropTable('changeslog');
	}
}