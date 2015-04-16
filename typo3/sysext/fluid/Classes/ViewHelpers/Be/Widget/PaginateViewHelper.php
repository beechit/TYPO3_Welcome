<?php
namespace TYPO3\CMS\Fluid\ViewHelpers\Be\Widget;

/*                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */
/**
 * This ViewHelper renders a Pagination of objects for the TYPO3 Backend.
 *
 * = Examples =
 *
 * <code title="required arguments">
 * <f:be.widget.paginate objects="{blogs}" as="paginatedBlogs">
 * use {paginatedBlogs} as you used {blogs} before, most certainly inside
 * a <f:for> loop.
 * </f:be.widget.paginate>
 * </code>
 *
 * <code title="full configuration">
 * <f:be.widget.paginate objects="{blogs}" as="paginatedBlogs" configuration="{itemsPerPage: 5, insertAbove: 1, insertBelow: 0, recordsLabel: 'MyRecords'}">
 * use {paginatedBlogs} as you used {blogs} before, most certainly inside
 * a <f:for> loop.
 * </f:be.widget.paginate>
 * The recordsLabel can be used to replace the text in "Records 1 - 99" with a label of your own choice
 * </code>
 *
 * = Performance characteristics =
 *
 * In the above examples, it looks like {blogs} contains all Blog objects, thus
 * you might wonder if all objects were fetched from the database.
 * However, the blogs are NOT fetched from the database until you actually use them,
 * so the paginate ViewHelper will adjust the query sent to the database and receive
 * only the small subset of objects.
 * So, there is no negative performance overhead in using the Be Paginate Widget.
 *
 * @api
 */
class PaginateViewHelper extends \TYPO3\CMS\Fluid\Core\Widget\AbstractWidgetViewHelper {

	/**
	 * @var \TYPO3\CMS\Fluid\ViewHelpers\Be\Widget\Controller\PaginateController
	 * @inject
	 */
	protected $controller;

	/**
	 * @param \TYPO3\CMS\Extbase\Persistence\QueryResultInterface $objects
	 * @param string $as
	 * @param array $configuration
	 * @return string
	 */
	public function render(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface $objects, $as, array $configuration = array('itemsPerPage' => 10, 'insertAbove' => FALSE, 'insertBelow' => TRUE, 'recordsLabel' => '')) {
		return $this->initiateSubRequest();
	}

}
