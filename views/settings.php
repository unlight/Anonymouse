<?php defined('APPLICATION') or die();?>

<h1><?php echo T('Anonymous post settings');?></h1>

<div class="Info">By default anonymous posting allowed for guests with permission to view category.
<?php 
/*echo ($this->AnonymouseCategory === False) ? '<code>Categories for anonymous posting is not set</code>' : '';
*/
?>
</div>

<?php echo $this->Form->Open(); ?>
<?php echo $this->Form->Errors(); ?>

<?php 
//public function CheckBoxList($FieldName, $DataSet, $ValueDataSet, $Attributes){
echo $this->Form->CheckBoxList('Plugins.Anonymouse.Category', $this->CategoryData, $this->AnonymouseCategory, array('ValueField' => 'CategoryID', 'TextField' => 'Name'));
?>

<?php echo $this->Form->Button('Save'); ?>
<?php echo $this->Form->Button('Reset to defaults'); ?>


<?php echo $this->Form->Close(); ?>