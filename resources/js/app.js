import Alpine from 'alpinejs';
import itemPicker from './item-picker';
import adjustmentForm from './adjustment-form';

window.Alpine = Alpine;

Alpine.data('itemPicker', itemPicker);
Alpine.data('adjustmentForm', adjustmentForm);

Alpine.start();
