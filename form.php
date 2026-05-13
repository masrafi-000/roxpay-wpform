<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="tdaf-wrapper" class="max-w-4xl mx-auto my-12 transition-all duration-500">

  <!-- ── Trust Header Section ──────────────────────────────────────── -->
  <div class="bg-white border border-slate-200 rounded-t-2xl border-b-0 p-6 sm:p-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-8">
      
      <!-- Brand & Title -->
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-full overflow-hidden border-4 border-slate-50 shadow-sm shrink-0">
          <svg viewBox="0 0 900 600" class="w-full h-full object-cover">
            <rect width="900" height="600" fill="#ED1C24"/>
            <rect width="900" height="400" y="100" fill="#fff"/>
            <rect width="900" height="200" y="200" fill="#241D4E"/>
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-black text-slate-900 leading-tight">Thailand Digital Arrival Card</h2>
          <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400 mt-1">Official Processing Partner</p>
        </div>
      </div>

      <!-- Trust Points -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 md:gap-10">
        <div class="space-y-1">
          <p class="text-[10px] font-black uppercase tracking-widest text-red-600">Fastest Delivery</p>
          <div class="flex items-center gap-2 text-slate-700 font-bold text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>30 mins</span>
          </div>
        </div>

        <div class="space-y-1">
          <p class="text-[10px] font-black uppercase tracking-widest text-red-600">Approval Rate</p>
          <div class="flex items-center gap-2 text-slate-700 font-bold text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z"/></svg>
            <span>99% Success</span>
          </div>
        </div>

        <div class="space-y-1">
          <p class="text-[10px] font-black uppercase tracking-widest text-red-600">Secure & Safe</p>
          <div class="flex items-center gap-2 text-slate-700 font-bold text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>256-bit SSL</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Main Form Card ────────────────────────────────────────────── -->
  <div class="bg-white border border-slate-200 rounded-b-2xl shadow-xl p-6 sm:p-10 font-sans text-slate-900 transition-all duration-500">

    <!-- ── Step indicator ──────────────────────────────────────────────── -->
    <div class="flex items-center justify-between mb-12 relative px-4" id="tdaf-step-indicator">
      <div class="absolute top-4 left-0 right-0 h-[1px] bg-slate-100 z-0 mx-8"></div>
      
      <?php
      $steps = [
        1 => 'Personal',
        2 => 'Travel',
        3 => 'Confirm',
      ];
      foreach ( $steps as $num => $label ) : ?>
        <div class="tdaf-step-item flex flex-col items-center z-10 group relative" data-step="<?php echo $num; ?>">
          <div class="tdaf-step-circle w-9 h-9 rounded-full bg-white border-2 border-slate-100 flex items-center justify-center font-bold text-sm text-slate-300 transition-all duration-300 group-[.active]:border-red-600 group-[.active]:text-red-600 group-[.active]:ring-4 group-[.active]:ring-red-50 group-[.completed]:bg-red-600 group-[.completed]:border-red-600 group-[.completed]:text-white">
            <span class="tdaf-step-num group-[.completed]:hidden"><?php echo $num; ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="tdaf-step-check hidden group-[.completed]:block"><polyline points="20 6 9 17 4 12"></polyline></svg>
          </div>
          <span class="tdaf-step-label text-[10px] uppercase tracking-widest font-black text-slate-300 mt-3 group-[.active]:text-slate-900 group-[.completed]:text-slate-900 hidden sm:block">
            <?php echo esc_html( $label ); ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── STEP 1 : Personal Information ──────────────────────────────── -->
    <div class="tdaf-panel space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500" id="tdaf-panel-1">
      
      <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Arrival Date <span class="text-red-500">*</span></label>
            <input type="date" name="arrival_date" id="arrival_date" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Departure Date <span class="text-red-500">*</span></label>
            <input type="date" name="departure_date" id="departure_date" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">First Name <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" id="first_name" placeholder="John" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Last Name <span class="text-red-500">*</span></label>
            <input type="text" name="last_name" id="last_name" placeholder="Doe" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Birth Date <span class="text-red-500">*</span></label>
            <input type="date" name="birth_date" id="birth_date" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Nationality <span class="text-red-500">*</span></label>
            <select name="nationality" id="nationality" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all appearance-none bg-[right_1rem_center] bg-no-repeat bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%2394A3B8%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22/%3E%3C/svg%3E');">
              <option value="">Select Nationality</option>
              <?php
              $countries = tdaf_get_countries();
              foreach ( $countries as $code => $name ) {
                echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
              }
              ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Passport No. <span class="text-red-500">*</span></label>
            <input type="text" name="passport_no" id="passport_no" placeholder="Passport Number" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Country of Residence <span class="text-red-500">*</span></label>
            <input type="text" name="country_residence" id="country_residence" placeholder="Country" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" id="email" placeholder="m@example.com" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Phone <span class="text-red-500">*</span></label>
            <input type="tel" name="phone" id="phone" placeholder="+1 (555) 000-0000" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Gender <span class="text-red-500">*</span></label>
            <div class="flex gap-6 py-2">
              <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium group"><input type="radio" name="gender" value="male" class="w-4 h-4 accent-red-600"> <span class="group-hover:text-red-600 transition-colors">Male</span></label>
              <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium group"><input type="radio" name="gender" value="female" class="w-4 h-4 accent-red-600"> <span class="group-hover:text-red-600 transition-colors">Female</span></label>
              <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium group"><input type="radio" name="gender" value="other" class="w-4 h-4 accent-red-600"> <span class="group-hover:text-red-600 transition-colors">Other</span></label>
            </div>
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Passport Image</label>
            <input type="file" name="passport_image" id="passport_image" accept="image/*" class="w-full text-xs text-slate-400 file:mr-4 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:bg-slate-900 file:text-white hover:file:bg-slate-800 cursor-pointer">
          </div>
        </div>
      </div>

      <!-- ── Add Other Traveler ──────────────────────────────────────── -->
      <div class="pt-8 border-t border-slate-100 space-y-6">
        <div id="tdaf-travelers-container" class="space-y-8">
          <!-- Dynamically injected traveler cards -->
        </div>

        <button type="button" id="tdaf-add-traveler-btn" class="flex items-center justify-center gap-3 w-full py-4 border-2 border-dashed border-slate-200 rounded-2xl text-xs font-black uppercase tracking-widest text-slate-400 hover:bg-slate-50 hover:border-red-200 hover:text-red-600 transition-all group">
          <span class="text-xl group-hover:scale-125 transition-transform">+</span> Add Another Traveler
        </button>
      </div>

      <div class="flex justify-end pt-6">
        <button type="button" class="px-10 py-3.5 bg-slate-900 text-white rounded-xl font-bold text-sm shadow-xl shadow-slate-200 hover:bg-red-600 hover:-translate-y-0.5 transition-all active:scale-95" id="tdaf-next-1">Next Step →</button>
      </div>
    </div><!-- /panel-1 -->

    <!-- ── STEP 2 : Travel Information ────────────────────────────────── -->
    <div class="tdaf-panel hidden space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500" id="tdaf-panel-2">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Departure Country <span class="text-red-500">*</span></label>
          <input type="text" name="departure_country" id="departure_country" placeholder="Country" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
        </div>
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Purpose <span class="text-red-500">*</span></label>
          <select name="purpose" id="purpose" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all appearance-none bg-[right_1rem_center] bg-no-repeat bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%2394A3B8%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22/%3E%3C/svg%3E');">
            <option value="">Select Purpose</option>
            <option>Tourism</option>
            <option>Business</option>
            <option>Transit</option>
            <option>Board Cruiseship</option>
            <option>Education</option>
            <option>Medical</option>
            <option>Other</option>
          </select>
        </div>
      </div>

      <div class="space-y-2">
        <label class="text-xs font-black uppercase tracking-wider text-slate-500">Flight Number <span class="text-red-500">*</span></label>
        <input type="text" name="flight_number" id="flight_number" placeholder="TG 401" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
      </div>

      <div class="pt-6 border-t border-slate-100">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600 mb-6">Stay Information</h3>
        <div class="space-y-6">
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Province <span class="text-red-500">*</span></label>
            <input type="text" name="hotel_province" id="hotel_province" placeholder="Province" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
          </div>
          <div class="space-y-2">
            <label class="text-xs font-black uppercase tracking-wider text-slate-500">Address or Name of Hotel <span class="text-red-500">*</span></label>
            <textarea name="hotel_address" id="hotel_address" rows="3" placeholder="Full address" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50/30 text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all"></textarea>
          </div>
        </div>
      </div>

      <div class="flex justify-between pt-6">
        <button type="button" class="px-6 py-3 border-2 border-slate-100 text-slate-400 rounded-xl font-bold text-sm hover:bg-slate-50 hover:text-slate-600 transition-all" id="tdaf-prev-2">← Back</button>
        <button type="button" class="px-10 py-3.5 bg-slate-900 text-white rounded-xl font-bold text-sm shadow-xl shadow-slate-200 hover:bg-red-600 hover:-translate-y-0.5 transition-all active:scale-95" id="tdaf-next-2">Next Step →</button>
      </div>
    </div><!-- /panel-2 -->

    <!-- ── STEP 3 : Confirmation ───────────────────────────────────────── -->
    <div class="tdaf-panel hidden space-y-10 animate-in fade-in slide-in-from-bottom-4 duration-500" id="tdaf-panel-3">
      <div class="space-y-5">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600">Select Processing Time</h3>
        
        <div class="grid grid-cols-1 gap-4">
          <?php
          $plans = [
            'standard' => ['name' => 'Standard', 'desc' => 'Within 24 hours', 'price' => '$59.99'],
            'express'  => ['name' => 'Express', 'desc' => 'Under 4 hours', 'price' => '$79.99'],
            'priority' => ['name' => 'Priority', 'desc' => 'Under 1 hour', 'price' => '$109.99'],
          ];
          foreach ( $plans as $val => $p ) : ?>
          <label class="relative block cursor-pointer group">
            <input type="radio" name="service" value="<?php echo $val; ?>" <?php echo $val === 'standard' ? 'checked' : ''; ?> class="peer sr-only">
            <div class="p-5 rounded-2xl border-2 border-slate-100 bg-white peer-checked:border-red-600 peer-checked:bg-red-50/30 transition-all hover:bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="space-y-1">
                <p class="font-bold text-sm text-slate-900"><?php echo $p['name']; ?> Processing</p>
                <p class="text-xs text-slate-500"><?php echo $p['desc']; ?></p>
              </div>
              <p class="text-sm font-black text-slate-900"><?php echo $p['price']; ?></p>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Upsell -->
      <div class="p-6 rounded-2xl bg-slate-950 text-white space-y-4 shadow-2xl">
        <div class="flex items-center justify-between">
          <h4 class="font-bold text-sm">Travel eSIM Add-on</h4>
          <span class="px-2.5 py-1 bg-red-600 rounded-full text-[9px] font-black uppercase tracking-widest">Recommended</span>
        </div>
        <label class="flex items-start gap-4 cursor-pointer group">
          <input type="checkbox" name="esim" id="esim_addon" value="1" class="mt-1 w-5 h-5 accent-red-600 rounded-lg">
          <div class="space-y-1">
            <span class="text-sm font-bold block group-hover:text-red-400 transition-colors">Instant High-Speed 5G Internet — Only $4.00</span>
            <p class="text-xs text-slate-400 leading-relaxed">No physical SIM needed. Get your QR activation code by email immediately after payment.</p>
          </div>
        </label>
      </div>

      <!-- Order Summary -->
      <div class="space-y-5">
        <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600">Order Summary</h3>
        <div class="border-2 border-slate-100 rounded-2xl overflow-hidden">
          <table class="w-full text-xs">
            <thead class="bg-slate-50/50 border-b-2 border-slate-100 hidden sm:table-header-group">
              <tr>
                <th class="text-left p-4 font-black uppercase tracking-widest text-slate-400">Item</th>
                <th class="text-center p-4 font-black uppercase tracking-widest text-slate-400">Qty</th>
                <th class="text-right p-4 font-black uppercase tracking-widest text-slate-400">Total</th>
              </tr>
            </thead>
            <tbody id="tdaf-summary-rows" class="divide-y divide-slate-50">
              <!-- Dynamic Rows will be injected by JS -->
            </tbody>
            <tfoot class="bg-slate-50/50 font-black border-t-2 border-slate-100">
              <tr class="flex flex-col sm:table-row p-4 sm:p-0">
                <td colspan="2" class="sm:p-4 text-sm">Total Amount</td>
                <td id="tdaf-total-amount" class="sm:p-4 sm:text-right text-lg text-red-600 mt-1 sm:mt-0">$59.99</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="flex justify-between pt-6">
        <button type="button" class="px-6 py-3 border-2 border-slate-100 text-slate-400 rounded-xl font-bold text-sm hover:bg-slate-50 hover:text-slate-600 transition-all" id="tdaf-prev-3">← Back</button>
        <button type="button" class="px-10 py-4 bg-red-600 text-white rounded-xl font-black text-sm shadow-xl shadow-red-100 hover:bg-red-700 hover:-translate-y-0.5 transition-all active:scale-95" id="tdaf-submit">Confirm & Pay Securely</button>
      </div>
    </div><!-- /panel-3 -->

    <!-- ── Success message ────────────────────────────────────────────── -->
    <div class="tdaf-panel hidden py-16 text-center space-y-6 animate-in fade-in zoom-in-95 duration-700" id="tdaf-panel-success">
      <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 border-2 border-emerald-100 shadow-inner">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </div>
      <div class="space-y-2">
        <h2 class="text-2xl font-black text-slate-900 tracking-tight">Application Received!</h2>
        <p class="text-sm text-slate-500 max-w-xs mx-auto leading-relaxed">Your Thailand Digital Arrival Card is being processed. Please check your email for confirmation.</p>
      </div>
      <div class="pt-6">
        <button type="button" onclick="window.location.reload()" class="px-8 py-3 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-red-600 transition-all shadow-xl shadow-slate-200">Start New Application</button>
      </div>
    </div>

  </div><!-- /Main Form Card -->

</div><!-- /#tdaf-wrapper -->

<!-- Traveler card template (hidden, cloned by JS) -->
<template id="tdaf-traveler-template">
  <div class="tdaf-traveler-card bg-slate-50/50 border-2 border-slate-100 rounded-3xl p-6 sm:p-8 relative group animate-in fade-in slide-in-from-top-4 duration-500">
    
    <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-100">
      <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600">Traveler <span class="tdaf-traveler-num"></span></h4>
      <button type="button" class="tdaf-remove-traveler inline-flex items-center justify-center rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-400 hover:bg-red-50 hover:text-red-600 px-3 py-1 transition-all">Remove</button>
    </div>
    
    <div class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">First Name <span class="text-red-500">*</span></label>
          <input type="text" name="travelers[0][first_name]" placeholder="First Name" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
        </div>
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Last Name <span class="text-red-500">*</span></label>
          <input type="text" name="travelers[0][last_name]" placeholder="Last Name" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Date of Birth</label>
          <input type="date" name="travelers[0][dob]" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
        </div>
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Nationality</label>
          <select name="travelers[0][nationality]" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all appearance-none bg-[right_1rem_center] bg-no-repeat bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%2394A3B8%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22/%3E%3C/svg%3E');">
            <option value="">Select Nationality</option>
            <?php
            $countries = tdaf_get_countries();
            foreach ( $countries as $code => $name ) {
              echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
            }
            ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Passport Number</label>
          <input type="text" name="travelers[0][passport]" placeholder="Passport Number" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 transition-all">
        </div>
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-wider text-slate-500">Gender</label>
          <div class="flex gap-6 py-2">
            <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium group"><input type="radio" name="travelers[0][gender]" value="male" class="w-4 h-4 accent-red-600"> <span class="group-hover:text-red-600 transition-colors text-slate-600">Male</span></label>
            <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium group"><input type="radio" name="travelers[0][gender]" value="female" class="w-4 h-4 accent-red-600"> <span class="group-hover:text-red-600 transition-colors text-slate-600">Female</span></label>
            <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium group"><input type="radio" name="travelers[0][gender]" value="other" class="w-4 h-4 accent-red-600"> <span class="group-hover:text-red-600 transition-colors text-slate-600">Other</span></label>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>