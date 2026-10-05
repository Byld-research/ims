import Alpine from 'alpinejs';
import itemPicker from './item-picker';

window.Alpine = Alpine;

Alpine.data('itemPicker', itemPicker);

Alpine.start();
