<?php
/**
 * COmanage Registry CO Bread Crumb
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v0.9.4
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
?>

<div id="pagination">
  <span class="paginationCounter">
    <?php
      // pagination information
      print $this->Paginator->counter(array(
        'format' => _txt('in.pagination.format')
      ));
    ?>
  </span>
  <?php if($this->paginator->hasPage(2)): ?>
    <span class="paginationPrev">
      <?php
        // Shows the previous link
        print $this->Paginator->prev(
          _txt('op.previous'),
          null,
          null,
          array('class' => 'disabled')
        );
      ?>
    </span>
    <span class="paginationNumbers">
      <?php
        // Shows the page numbers
        print $this->Paginator->numbers();
      ?>
    </span>
    <span class="paginationNext">
      <?php
        // Shows the next link
        print $this->Paginator->next(
          _txt('op.next'),
          null,
          null,
          array('class' => 'disabled')
        );
      ?>
    </span>
  <?php endif; ?>
</div>

