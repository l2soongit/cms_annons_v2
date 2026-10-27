/*
 * Сторінка гри в стилі Lineage 2 (design/game/lineage2.html): спільна логіка + іскри в банері.
 */
import { $ } from './game-page';

const rnd = (a, b) => a + Math.random() * (b - a);
const motes = $('#motes');
if (motes) motes.innerHTML = Array.from({ length: 22 }, () => `<i class="mote" style="left:${rnd(30, 98)}%;bottom:${rnd(0, 45)}%;animation-delay:-${rnd(0, 7)}s;animation-duration:${rnd(5, 9)}s"></i>`).join('');