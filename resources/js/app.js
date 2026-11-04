import './bootstrap';
import '../css/barberia.css';
import '@fontsource-variable/bricolage-grotesque';
import '@fontsource-variable/dm-sans';
import 'bootstrap-icons/font/bootstrap-icons.css';
import { bbTimePicker } from './timepicker';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

window.bbTimePicker = bbTimePicker;
window.bbDashboard = () => import('./dashboard.js');