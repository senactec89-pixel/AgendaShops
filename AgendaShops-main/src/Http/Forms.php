<?php
declare(strict_types=1);
namespace AgendaShops\Http;

final class Forms
{
    public static function open(string $action, array $hidden = [], string $return = '', bool $upload = false): string
    {
        $html = '<form method="post" action="/acao/' . e($action) . '"' . ($upload ? ' enctype="multipart/form-data"' : '') . '>';
        $hidden = ['csrf'=>$_SESSION['csrf'] ?? '', 'return'=>$return] + $hidden;
        foreach ($hidden as $key=>$value) { $html .= '<input type="hidden" name="' . e($key) . '" value="' . e((string)$value) . '">'; }
        return $html;
    }
    public static function input(string $name, string $label, string $value = '', string $type = 'text', string $attributes = ''): string
    {
        return '<label>' . e($label) . '<input name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '" ' . $attributes . '></label>';
    }
    public static function area(string $name, string $label, string $value = '', string $attributes = 'required'): string
    {
        return '<label>' . e($label) . '<textarea name="' . e($name) . '" ' . $attributes . '>' . e($value) . '</textarea></label>';
    }
    public static function select(string $name, string $label, array $options, string $selected = '', string $attributes = 'required'): string
    {
        $html = '<label>' . e($label) . '<select name="' . e($name) . '" ' . $attributes . '>';
        foreach ($options as $value=>$text) { $html .= '<option value="' . e((string)$value) . '"' . ((string)$value === $selected ? ' selected' : '') . '>' . e((string)$text) . '</option>'; }
        return $html . '</select></label>';
    }
    public static function check(string $name, string $label, bool $checked = false, string $value = '1'): string
    {
        return '<label class="check"><input type="checkbox" name="' . e($name) . '" value="' . e($value) . '"' . ($checked ? ' checked' : '') . '> ' . e($label) . '</label>';
    }
    public static function close(string $label = 'Salvar'): string { return '<button class="button button-primary" type="submit">' . e($label) . '</button></form>'; }
    public static function days(string $days = '1,2,3,4,5,6'): string
    {
        $html = '<fieldset><legend>Dias de atendimento</legend>';
        foreach (['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','7'=>'Dom'] as $n=>$name) { $html .= self::check('weekdays[]',$name,in_array((string)$n,explode(',',$days),true),(string)$n); }
        return $html . '</fieldset>';
    }
}
