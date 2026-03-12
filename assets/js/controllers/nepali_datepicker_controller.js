import { Controller } from '@hotwired/stimulus';

/**
 * NepaliCalendarBundle — Stimulus datepicker controller
 *
 * Values (set as data attributes on the controller element):
 *   data-nepali-datepicker-calendar-json-path-value  — URL to nepali_calendar.json
 *   data-nepali-datepicker-picker-type-value          — 'ad' | 'bs'  (initial mode)
 */
export default class extends Controller {

  static targets = [
    'input', 'datepicker', 'calendarGrid',
    'monthDropdown', 'yearDropdown', 'monthText', 'yearText',
  ];

  static values = {
    calendarJsonPath: {
      type: String,
      default: 'https://fitnechnepal.blr1.cdn.digitaloceanspaces.com/Calendar/nepali_calendar.json',
    },
    pickerType: { type: String, default: 'ad' },
  };

  // ─── Lifecycle ────────────────────────────────────────────────

  connect() {
    this._loadCalendarData();
  }

  // ─── Bootstrap ────────────────────────────────────────────────

  async _loadCalendarData() {
    const CACHE_KEY  = `nepali_cal_${this.calendarJsonPathValue}`;
    const CACHE_TTL  = 5 * 60 * 1000;

    try {
      const cached = sessionStorage.getItem(CACHE_KEY);
      if (cached) {
        const { ts, data } = JSON.parse(cached);
        if (Date.now() - ts < CACHE_TTL) {
          this._calendarData = data;
          this._boot();
          return;
        }
      }

      const res = await fetch(this.calendarJsonPathValue);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      this._calendarData = await res.json();
      sessionStorage.setItem(CACHE_KEY, JSON.stringify({ ts: Date.now(), data: this._calendarData }));
      this._boot();

    } catch (err) {
      console.error('[NepaliCalendarBundle] Failed to load calendar data:', err);
    }
  }

  _boot() {
    // Inject picker markup after the input
    this.inputTarget.insertAdjacentElement('afterend', this._buildPickerElement());

    this._isNepaliMode = this.pickerTypeValue === 'bs';

    this._monthNamesNe  = ['बैशाख','जेठ','असार','श्रावण','भाद्र','आश्विन','कार्तिक','मंसिर','पौष','माघ','फाल्गुन','चैत्र'];
    this._monthNamesEn  = ['Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'];
    this._monthNamesAD  = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    this._dayNamesNe    = ['आइत','सोम','मंगल','बुध','बिही','शुक्र','शनि'];
    this._dayNamesEn    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    this._viewDateAD    = new Date();
    this._viewDateBS    = this._adToBS(this._viewDateAD);
    this._selectedDate  = { bs: this._viewDateBS, ad: new Date(this._viewDateAD) };

    this._bindEvents();
    this._updateInput();
    this._renderCalendar();
  }

  // ─── Event binding ────────────────────────────────────────────

  _bindEvents() {
    this.inputTarget.addEventListener('click', () => {
      this.datepickerTarget.classList.toggle('active');
      if (this.datepickerTarget.classList.contains('active')) this._renderCalendar();
    });

    document.addEventListener('click', (e) => {
      if (!this.element.contains(e.target)) {
        this.datepickerTarget.classList.remove('active');
        this._closeDropdowns();
      }
    });
  }

  // ─── Stimulus actions ─────────────────────────────────────────

  toggleToAd(e) { this._isNepaliMode = false;  this._onModeToggle(e); }
  toggleToBs(e) { this._isNepaliMode = true;   this._onModeToggle(e); }

  onPrevMonthClick(e) { e.stopPropagation(); this._shiftMonth(-1); }
  onNextMonthClick(e) { e.stopPropagation(); this._shiftMonth(1);  }

  onMonthButtonClick(e) {
    e.stopPropagation();
    this.yearDropdownTarget.classList.remove('active');
    this.monthDropdownTarget.classList.toggle('active');
    if (this.monthDropdownTarget.classList.contains('active')) this._renderMonthDropdown();
  }

  onYearButtonClick(e) {
    e.stopPropagation();
    this.monthDropdownTarget.classList.remove('active');
    this.yearDropdownTarget.classList.toggle('active');
    if (this.yearDropdownTarget.classList.contains('active')) this._renderYearDropdown();
  }

  selectToday(e) {
    e.stopPropagation();
    const today = new Date();
    this._selectADDate(today);
    this._viewDateAD = new Date(today);
    this._viewDateBS = this._adToBS(today);
    this._renderCalendar();
  }

  clearSelection() {
    this._selectedDate = null;
    this.inputTarget.value = '';
    this._renderCalendar();
  }

  // ─── Internal helpers ─────────────────────────────────────────

  _onModeToggle(e) {
    this.element.querySelectorAll('.calendar-mode').forEach(el => el.classList.remove('active'));
    e.target.classList.add('active');
    this._closeDropdowns();
    this._renderCalendar();
  }

  _closeDropdowns() {
    this.monthDropdownTarget.classList.remove('active');
    this.yearDropdownTarget.classList.remove('active');
  }

  _shiftMonth(delta) {
    this._closeDropdowns();
    this._viewDateBS.month += delta;
    if      (this._viewDateBS.month > 12) { this._viewDateBS.month = 1;  this._viewDateBS.year++; }
    else if (this._viewDateBS.month < 1)  { this._viewDateBS.month = 12; this._viewDateBS.year--; }
    this._viewDateAD.setMonth(this._viewDateAD.getMonth() + delta);
    this._renderCalendar();
  }

  // ─── Dropdowns ────────────────────────────────────────────────

  _renderMonthDropdown() {
    this.monthDropdownTarget.innerHTML = '';
    const names   = this._isNepaliMode ? this._monthNamesNe : this._monthNamesAD;
    const current = this._isNepaliMode ? this._viewDateBS.month : this._viewDateAD.getMonth() + 1;

    names.forEach((name, i) => {
      const item = document.createElement('div');
      item.className = 'dropdown-item' + (i + 1 === current ? ' selected' : '');
      item.textContent = name;
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        this._viewDateBS.month = i + 1;
        this._viewDateAD.setMonth(i);
        this.monthDropdownTarget.classList.remove('active');
        this._renderCalendar();
      });
      this.monthDropdownTarget.appendChild(item);
    });
  }

  _renderYearDropdown() {
    this.yearDropdownTarget.innerHTML = '';
    const [startYear, endYear, currentYear] = this._isNepaliMode
      ? [2070, 2090, this._viewDateBS.year]
      : [2010, 2035, this._viewDateAD.getFullYear()];

    for (let yr = endYear; yr >= startYear; yr--) {
      const item = document.createElement('div');
      item.className = 'dropdown-item' + (yr === currentYear ? ' selected' : '');
      item.textContent = this._isNepaliMode ? this._toNepaliDigits(yr) : yr;
      if (yr === currentYear) setTimeout(() => item.scrollIntoView({ block: 'center' }), 0);
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        this._viewDateBS.year = yr;
        this._viewDateAD.setFullYear(yr);
        this.yearDropdownTarget.classList.remove('active');
        this._renderCalendar();
      });
      this.yearDropdownTarget.appendChild(item);
    }
  }

  // ─── Calendar rendering ───────────────────────────────────────

  _renderCalendar() {
    this._closeDropdowns();
    this._isNepaliMode ? this._renderBSCalendar() : this._renderADCalendar();
  }

  _renderBSCalendar() {
    this.monthTextTarget.textContent = this._monthNamesNe[this._viewDateBS.month - 1];
    this.yearTextTarget.textContent  = this._toNepaliDigits(this._viewDateBS.year);

    const grid        = this.calendarGridTarget;
    grid.innerHTML    = '';
    this._dayNamesNe.forEach(d => grid.appendChild(this._el('div', 'day-name', d)));

    const daysInMonth  = this._bsDaysInMonth(this._viewDateBS.year, this._viewDateBS.month);
    const firstWeekday = this._bsFirstWeekday(this._viewDateBS.year, this._viewDateBS.month);
    const todayBS      = this._adToBS(new Date());

    for (let i = 0; i < firstWeekday; i++) grid.appendChild(this._el('div', 'day other-month'));

    for (let day = 1; day <= daysInMonth; day++) {
      const el = this._el('div', 'day', this._toNepaliDigits(day));
      const isToday =
        todayBS.year  === this._viewDateBS.year &&
        todayBS.month === this._viewDateBS.month &&
        todayBS.day   === day;
      const isSelected =
        this._selectedDate &&
        this._selectedDate.bs.year  === this._viewDateBS.year &&
        this._selectedDate.bs.month === this._viewDateBS.month &&
        this._selectedDate.bs.day   === day;

      if (isToday)    el.classList.add('today');
      if (isSelected) el.classList.add('selected');

      el.addEventListener('click', () => this._selectBSDate(this._viewDateBS.year, this._viewDateBS.month, day));
      grid.appendChild(el);
    }

    this._fillTrailingCells(grid);
  }

  _renderADCalendar() {
    const year  = this._viewDateAD.getFullYear();
    const month = this._viewDateAD.getMonth();

    this.monthTextTarget.textContent = this._monthNamesAD[month];
    this.yearTextTarget.textContent  = year;

    const grid = this.calendarGridTarget;
    grid.innerHTML = '';
    this._dayNamesEn.forEach(d => grid.appendChild(this._el('div', 'day-name', d)));

    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    for (let i = 0; i < firstDay; i++) grid.appendChild(this._el('div', 'day other-month'));

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      const el   = this._el('div', 'day', String(day));

      if (this._isToday(date))                                             el.classList.add('today');
      if (this._selectedDate && this._isSameDay(date, this._selectedDate.ad)) el.classList.add('selected');

      el.addEventListener('click', () => this._selectADDate(date));
      grid.appendChild(el);
    }

    this._fillTrailingCells(grid);
  }

  _fillTrailingCells(grid) {
    const cells    = grid.children.length - 7; // subtract header row
    const trailing = Math.ceil(cells / 7) * 7 - cells;
    for (let i = 0; i < trailing; i++) grid.appendChild(this._el('div', 'day other-month'));
  }

  // ─── Selection ────────────────────────────────────────────────

  _selectBSDate(year, month, day) {
    this._selectedDate = { bs: { year, month, day }, ad: this._bsToAD(year, month, day) };
    this._updateInput();
    this._renderCalendar();
  }

  _selectADDate(date) {
    this._selectedDate = { bs: this._adToBS(date), ad: new Date(date) };
    this._updateInput();
    this._renderCalendar();
  }

  _updateInput() {
    if (!this._selectedDate) return;
    const { bs, ad } = this._selectedDate;
    const bsStr = `${this._toNepaliDigits(bs.year)}-${this._toNepaliDigits(bs.month)}-${this._toNepaliDigits(bs.day)}`;
    const adStr = this._formatAD(ad);
    this.inputTarget.value = `${adStr} AD (${bsStr} BS)`;
  }

  // ─── Date conversion ─────────────────────────────────────────

  _adToBS(date) {
    const target = new Date(date);
    target.setHours(0, 0, 0, 0);

    for (const [bsYear, months] of Object.entries(this._calendarData)) {
      for (let i = 0; i < months.length; i++) {
        const [monthNum, startStr, totalDays] = months[i];
        const next = months[i + 1] ?? null;

        const start = new Date(startStr); start.setHours(0, 0, 0, 0);
        const end   = next ? new Date(next[1]) : new Date('2100-01-01');
        end.setHours(0, 0, 0, 0);

        if (target >= start && target < end) {
          const day = Math.floor((target - start) / 86_400_000) + 1;
          if (day >= 1 && day <= totalDays) {
            return { year: parseInt(bsYear), month: monthNum, day };
          }
        }
      }
    }
    throw new Error('Could not convert AD to BS: ' + date);
  }

  _bsToAD(year, month, day) {
    const months = this._calendarData[String(year)];
    if (!months) throw new Error(`Unsupported BS year: ${year}`);
    const [, startStr, totalDays] = months[month - 1];
    if (day < 1 || day > totalDays) throw new Error(`Invalid day ${day} for BS ${year}-${month}`);
    const [sy, sm, sd] = startStr.split('-').map(Number);
    const result = new Date(sy, sm - 1, sd);
    result.setDate(sd + (day - 1));
    return result;
  }

  _bsDaysInMonth(year, month) {
    return this._calendarData[String(year)][month - 1][2];
  }

  _bsFirstWeekday(year, month) {
    return new Date(this._calendarData[String(year)][month - 1][1]).getDay();
  }

  // ─── Utilities ────────────────────────────────────────────────

  _toNepaliDigits(n) {
    const map = ['०','१','२','३','४','५','६','७','८','९'];
    return String(n).replace(/\d/g, d => map[parseInt(d)]);
  }

  _formatAD(date) {
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
  }

  _isToday(date)          { return this._isSameDay(date, new Date()); }
  _isSameDay(a, b)        { return a.getDate() === b.getDate() && a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear(); }
  _el(tag, cls, text = '') {
    const el = document.createElement(tag);
    el.className   = cls;
    el.textContent = text;
    return el;
  }

  // ─── Picker HTML ──────────────────────────────────────────────

  _buildPickerElement() {
    const div = document.createElement('div');
    div.className = 'nepali-datepicker';
    div.dataset.nepaliDatepickerTarget = 'datepicker';
    div.innerHTML = `
      <div class="datepicker-header">
        <div class="calendar-mode-toggle">
          <button class="calendar-mode${!this._isNepaliMode ? ' active' : ''}" data-action="nepali-datepicker#toggleToAd">AD</button>
          <button class="calendar-mode${this._isNepaliMode  ? ' active' : ''}" data-action="nepali-datepicker#toggleToBs">BS</button>
        </div>
        <div class="month-year-selectors">
          <button class="nav-button" data-action="nepali-datepicker#onPrevMonthClick">‹</button>
          <div class="dropdown-wrapper">
            <button class="dropdown-button" data-action="nepali-datepicker#onMonthButtonClick">
              <span data-nepali-datepicker-target="monthText"></span><span>▼</span>
            </button>
            <div class="dropdown-menu" data-nepali-datepicker-target="monthDropdown"></div>
          </div>
          <div class="dropdown-wrapper">
            <button class="dropdown-button" data-action="nepali-datepicker#onYearButtonClick">
              <span data-nepali-datepicker-target="yearText"></span><span>▼</span>
            </button>
            <div class="dropdown-menu" data-nepali-datepicker-target="yearDropdown"></div>
          </div>
          <button class="nav-button" data-action="nepali-datepicker#onNextMonthClick">›</button>
        </div>
      </div>
      <div class="calendar-grid" data-nepali-datepicker-target="calendarGrid"></div>
      <div class="footer-buttons">
        <button class="btn-today" data-action="nepali-datepicker#selectToday">आज / Today</button>
        <button class="btn-clear" data-action="nepali-datepicker#clearSelection">Clear</button>
      </div>
    `;
    return div;
  }
}
