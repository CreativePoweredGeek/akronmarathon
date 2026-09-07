<div>
    <?php 
namespace BoldMinded\DataGrab\Dependency;

foreach ($elements as $element) {
    ?>
        <div class="text-gray mx-2">
            ⇂ <?php 
    echo \htmlspecialchars($element);
    ?>
        </div>
    <?php 
}
?>
</div>
<?php 
