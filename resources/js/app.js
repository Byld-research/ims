import Alpine from 'alpinejs';
import itemPicker from './item-picker';
import adjustmentForm from './adjustment-form';
import issueForm from './issue-form';
import transferForm from './transfer-form';

window.Alpine = Alpine;

Alpine.data('itemPicker', itemPicker);
Alpine.data('adjustmentForm', adjustmentForm);
Alpine.data('issueForm', issueForm);
Alpine.data('transferForm', transferForm);

Alpine.start();
