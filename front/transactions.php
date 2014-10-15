<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2014 Intelliants, LLC <http://www.intelliants.com>
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

if (!iaUsers::hasIdentity())
{
	return iaView::accessDenied();
}

$iaTransaction = $iaCore->factory('transaction');

if (iaView::REQUEST_JSON == $iaView->getRequestType())
{
	if (isset($_GET['amount']))
	{
		$output = array('error' => false);
		$amount = (float)$_GET['amount'];

		if ($amount > 0)
		{
			if ($amount >= (float)$iaCore->get('balance_min_amount'))
			{
				$transactionId = $iaTransaction->createInvoice(iaLanguage::get('member_balance'), $amount, 'balance', iaUsers::getIdentity(true), $profilePageUrl, 0, true);

				$output['url'] = IA_URL . 'pay' . IA_URL_DELIMITER . $transactionId . IA_URL_DELIMITER;
			}
			else
			{
				$output['error'] = iaLanguage::get('amount_less_min');
			}
		}
		else
		{
			$output['error'] = iaLanguage::get('amount_incorrect');
		}

		$iaView->assign($output);
	}
}

if (iaView::REQUEST_HTML == $iaView->getRequestType())
{
	$profilePageUrl = IA_URL . 'profile/';

	// forced update of funds
	$iaCore->factory('users')->getAuth(iaUsers::getIdentity()->id);
	//

	if (isset($_POST['amount']))
	{
		$amount = (float)$_POST['amount'];
		if ($amount > 0)
		{
			if ($amount >= (float)$iaCore->get('balance_min_amount'))
			{
				$iaTransaction->createInvoice(iaLanguage::get('member_balance'), $amount, 'balance', iaUsers::getIdentity(true), $profilePageUrl);
			}
			else
			{
				$iaView->setMessages(iaLanguage::get('amount_less_min'));
			}
		}
		else
		{
			$iaView->setMessages(iaLanguage::get('amount_incorrect'));
		}
	}

	$pagination = array(
		'page' => 1,
		'limit' => 10,
		'total' => 0,
		'template' => $profilePageUrl . 'balance/?page={page}'
	);

	$pagination['page'] = (isset($_GET['page']) && 1 < $_GET['page']) ? (int)$_GET['page'] : $pagination['page'];
	$pagination['page'] = ($pagination['page'] - 1) * $pagination['limit'];

	$transactions = $iaDb->all('SQL_CALC_FOUND_ROWS *', '`member_id` = ' . iaUsers::getIdentity()->id . ' ORDER BY `status`', $pagination['page'], $pagination['limit'], iaTransaction::getTable());
	$pagination['total'] = $iaDb->foundRows();

	$iaView->caption($iaView->title() . ': ' . number_format(iaUsers::getIdentity()->funds, 2, '.', '') . ' ' . $iaCore->get('currency'));

	$iaView->assign('pagination', $pagination);
	$iaView->assign('transactions', $transactions);

	$iaView->display('transactions');
}