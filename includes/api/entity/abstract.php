<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2016 Intelliants, LLC <http://www.intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @link http://www.subrion.org/
 *
 ******************************************************************************/

abstract class iaApiEntityAbstract
{
	protected $_request;
	protected $_response;

	protected $_table;

	protected $_iaCore;
	protected $_iaDb;


	public function init(){}
	public function __construct()
	{
		$iaCore = iaCore::instance();

		$this->_iaCore = $iaCore;
		$this->_iaDb = &$iaCore->iaDb;
	}

	public function getTable()
	{
		return $this->_table;
	}

	public function setRequest(iaApiRequest $request)
	{
		$this->_request = $request;
	}

	public function getRequest()
	{
		return $this->_request;
	}

	public function setResponse(iaApiResponse $response)
	{
		$this->_response = $response;
	}

	public function getResponse()
	{
		return $this->_response;
	}

	public function listResources($start, $limit)
	{
		return $this->_iaDb->all(iaDb::ALL_COLUMNS_SELECTION, null, $start, $limit, $this->getTable());
	}

	public function getResource($id)
	{
		$row = $this->_iaDb->row(iaDb::ALL_COLUMNS_SELECTION, iaDb::convertIds($id), $this->getTable());

		if (!$row)
		{
			throw new Exception('Resource does not exist', iaApiResponse::NOT_FOUND);
		}

		return $row;
	}

	public function deleteResource($id)
	{
		$row = $this->getOne($id);

		if (!$this->_iaDb->delete(iaDb::convertIds($row['id']), $this->getTable()))
		{
			throw new Exception('Could not delete a resource', iaApiResponse::INTERNAL_ERROR);
		}
	}

	public function updateResource(array $data, $id)
	{
		$this->_iaDb->update($data, iaDb::convertIds($id), null, $this->getTable());

		if (0 != $this->_iaDb->getErrorNumber())
		{
			throw new Exception('Could not update a resource', iaApiResponse::INTERNAL_ERROR);
		}
	}

	public function addResource(array $data)
	{
		$id = $this->_iaDb->insert($data, null, $this->getTable());

		$this->getResponse()->setCode($id ? iaApiResponse::CREATED : iaApiResponse::INTERNAL_ERROR);

		if ($id)
		{
			$this->getResponse()->setBody(array('id' => $id));
		}
	}
}