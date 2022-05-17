{{if $wrapper!="no"}}<div id="{{$qmc}}{{$field.name}}_wrapper" class="mb-3">{{/if}}

<label for="{{$qmc}}{{$field.name}}fs">{{$label}}
		{{if $qmcid}}<sup class="zuiqmid required">{{$qmcid}}</sup>{{/if}}	
</label>
<fieldset name="{{$qmc}}{{$field.name}}fs" id="id_{{$qmc}}{{$field.name}}_fs" title="{{$field.title}}">

<input id="{{$qmc}}{{$field.name}}n" 
	 name="{{$qmc}}{{$field.name}}n" 
	class="inline-block mr-1 text-center" style="width: 5rem;"  
	 type="number" 
{{if $field.min}} min="{{$field.min}}"{{/if}}
{{if $field.max}} max="{{$field.max}}"{{/if}}
	 size="{{$field.size}}" 
	value="{{$field.value}}" 
	title="{{$field.title}}">

{{foreach $rabot as $k=>$v}}
	<input  id="{{$qmc}}{{$field.name}}{{$k}}" name="{{$qmc}}{{$field.name}}"
	  		type="radio" value="{{$k}}" {{if $field.default==$k}} checked="checked"{{/if}}>
	<label for="{{$qmc}}{{$field.name}}{{$k}}">{{$v}}</label>
{{/foreach}}

</fieldset>

<small id="{{$qmc}}{{$field.name}}_help" class="form-text text-muted">{{$help}}</small>

{{if $wrapper!="no"}}</div>{{/if}}

{{*
  * Template field_duration.qmc.tpl
  * **********************************
  * Hilmar Runge, 2020.02
  * The template generates one input field for numeric values and a radio button group, where one 
  * (and only one or no) selection can be active. The primary intented use is for entering time/date
  * data in the form of amount (numeric) and the units (ie hours, days etc).
  * Instead of using positional array parameters, keyed (named) parameters are treated. Imo, named parameters
  * are easier to apply, the position does not matter and if one is not wanted or required, only omit it.
  *
  * The parameters in this template are:
  * ************************************
  * label 	A label for the whole. Optional.
  * help 	An optional explanation text.
  * qmc		Optional a qualified message component prefix, best use case is 3 letters lowercase and depends
  *			on the module or component used in the system. Part of id's and names in html and css.
  * qmcid	The qmc message id. Optional. Should be qmc+4digits+1charsufffix (8 chars uppercase).
  * field 	keyed array parameters:
  *			name 	The (unique) name of the elements also used for html ids,
  * 				will be suffixed by 'n' for the numeric input and 'u' for the units
  *			title	The title of the element
  *			legend 	a headline for the radio buttons (optional)
  *	rabot	the keyed array of radio buttons, where:
  *			k 	the key becomes the submitted value
  *			v 	the string value is the label text for the radio button.
  *
  * Example to apply in php like:
  * *****************************
  	$testcase = replace_macros(get_markup_template('field_radio_group.qmc.tpl'),
		array(	
			'label'	 	=> t('Exiration duration', 			
			'qmc'	 	=> 'zai', 			// not required
		 	'qmcid'	 	=> 'ZAI0000I',		// not required
		 	'wrapper' 	=> 'no',			// when no wrapper around is desired
		 	'field'		=> 					// fieldset properties
		 	array(
		 		'name'  => 'due', 			
		 		'min'  	=> 	"1", 			// the minimum value for the numeric input
		 		'max'  	=> 	"99", 			// the maximum value for the numeric input
		 		'size' 	=> 	"2", 			// the max digits for the numeric input
		 		'title' => 'time/date unit',
 				'default' => 'd'			// say 'default' => '' if none defaults (or omit)
		 	),
		 	'rabot'	=> 						// the radio buttons
			array(
 				'i'	=> 'Minute(s)',
 				'h' => 'Hour(s)'  ,
 				'd' => 'Day(s)'   ,
 				'w' => 'Week(s)'  ,
 				'm' => 'Month(s)' ,
 				'y' => 'Year(s)' 
 			)
		)
	);
  *
  *}}


