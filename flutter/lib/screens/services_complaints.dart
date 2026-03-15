// ─────────────────────────────────────────────
//  lib/screens/services/services_screen.dart
// ─────────────────────────────────────────────
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';
import 'dart:io';

// ══════════════════════════════════════════════
//  Services List Screen
// ══════════════════════════════════════════════
class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});
  @override State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  List<dynamic> _requests = [];
  bool _loading = true;

  static const _serviceTypes = [
    (type: 'birth_registration',    icon: Icons.child_care,      label: 'Birth Registration',      color: Colors.blue),
    (type: 'death_registration',    icon: Icons.heart_broken,    label: 'Death Registration',      color: Colors.grey),
    (type: 'marriage_registration', icon: Icons.favorite,        label: 'Marriage Registration',   color: Colors.pink),
    (type: 'migration_certificate', icon: Icons.directions_walk, label: 'Migration Certificate',   color: Colors.teal),
    (type: 'recommendation_letter', icon: Icons.description,     label: 'Recommendation Letter',   color: Colors.green),
    (type: 'business_registration', icon: Icons.store,           label: 'Business Registration',   color: Colors.orange),
  ];

  @override
  void initState() { super.initState(); _loadRequests(); }

  Future<void> _loadRequests() async {
    try {
      final res = await getIt<ApiService>().getMyServices();
      setState(() { _requests = res.data['data'] ?? []; _loading = false; });
    } catch (_) { setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Digital Services'),
          bottom: const TabBar(tabs: [Tab(text: 'New Request'), Tab(text: 'My Requests')]),
        ),
        body: TabBarView(children: [
          // ── New Request Tab ──
          GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 1.1),
            itemCount: _serviceTypes.length,
            itemBuilder: (ctx, i) {
              final s = _serviceTypes[i];
              return InkWell(
                onTap: () => ctx.push('/services/new', extra: s.type),
                borderRadius: BorderRadius.circular(12),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Container(
                        width: 52, height: 52,
                        decoration: BoxDecoration(color: s.color.withOpacity(0.1), shape: BoxShape.circle),
                        child: Icon(s.icon, color: s.color, size: 28),
                      ),
                      const SizedBox(height: 10),
                      Text(s.label, textAlign: TextAlign.center,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13), maxLines: 2, overflow: TextOverflow.ellipsis),
                    ]),
                  ),
                ),
              );
            },
          ),

          // ── My Requests Tab ──
          _loading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: _loadRequests,
                child: _requests.isEmpty
                  ? const Center(child: Text('No service requests yet'))
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _requests.length,
                      itemBuilder: (ctx, i) => _ServiceRequestCard(request: _requests[i]),
                    ),
              ),
        ]),
      ),
    );
  }
}

class _ServiceRequestCard extends StatelessWidget {
  final Map<String,dynamic> request;
  const _ServiceRequestCard({required this.request});

  Color get _statusColor => switch (request['status']) {
    'approved' || 'completed' => Colors.green,
    'rejected'                => Colors.red,
    'processing'              => Colors.blue,
    _                         => Colors.orange,
  };

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        title: Text(request['service_type'].toString().replaceAll('_',' ').toUpperCase(),
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        subtitle: Text(request['tracking_no'] ?? ''),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: _statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(20)),
          child: Text(request['status'].toString().toUpperCase(),
            style: TextStyle(color: _statusColor, fontSize: 11, fontWeight: FontWeight.bold)),
        ),
        onTap: () => context.push('/services/${request['tracking_no']}'),
      ),
    );
  }
}

// ══════════════════════════════════════════════
//  New Service Request Screen
// ══════════════════════════════════════════════
class NewServiceScreen extends StatefulWidget {
  final String type;
  const NewServiceScreen({super.key, required this.type});
  @override State<NewServiceScreen> createState() => _NewServiceScreenState();
}

class _NewServiceScreenState extends State<NewServiceScreen> {
  final _formKey   = GlobalKey<FormState>();
  final _formData  = <String, dynamic>{};
  int?  _wardNo;
  bool  _submitting = false;
  List<File> _docs  = [];

  String get _title => widget.type.replaceAll('_', ' ').split(' ')
      .map((w) => w[0].toUpperCase() + w.substring(1)).join(' ');

  List<_FormField> get _fields => switch (widget.type) {
    'birth_registration' => [
      _FormField('child_name',    'Child\'s Full Name',      TextInputType.text,  true),
      _FormField('dob',           'Date of Birth (YYYY-MM-DD)', TextInputType.datetime, true),
      _FormField('father_name',   'Father\'s Name',          TextInputType.text,  true),
      _FormField('mother_name',   'Mother\'s Name',          TextInputType.text,  true),
      _FormField('birth_place',   'Place of Birth',          TextInputType.text,  true),
    ],
    'death_registration' => [
      _FormField('deceased_name', 'Deceased\'s Full Name',   TextInputType.text,  true),
      _FormField('dod',           'Date of Death (YYYY-MM-DD)', TextInputType.datetime, true),
      _FormField('cause_of_death','Cause of Death',          TextInputType.text,  false),
      _FormField('relation',      'Your Relation',           TextInputType.text,  true),
    ],
    'marriage_registration' => [
      _FormField('groom_name',    'Groom\'s Name',           TextInputType.text,  true),
      _FormField('bride_name',    'Bride\'s Name',           TextInputType.text,  true),
      _FormField('marriage_date', 'Marriage Date',           TextInputType.datetime, true),
      _FormField('marriage_place','Marriage Place',          TextInputType.text,  true),
    ],
    'migration_certificate' => [
      _FormField('from_address',  'Moving From (Address)',   TextInputType.text,  true),
      _FormField('to_address',    'Moving To (Address)',     TextInputType.text,  true),
      _FormField('reason',        'Reason for Migration',    TextInputType.text,  false),
    ],
    _ => [
      _FormField('description', 'Description / Purpose', TextInputType.multiline, true),
    ],
  };

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _wardNo == null) return;
    _formKey.currentState!.save();
    setState(() => _submitting = true);

    try {
      final res = await getIt<ApiService>().submitService({
        'service_type': widget.type,
        'ward_no':      _wardNo,
        'form_data':    _formData,
      });

      final reqId      = res.data['request']['id'];
      final trackingNo = res.data['tracking_no'];

      // Upload documents
      for (final doc in _docs) {
        final form = FormData.fromMap({
          'document_type': 'supporting_document',
          'file': await MultipartFile.fromFile(doc.path, filename: doc.path.split('/').last),
        });
        await getIt<ApiService>().uploadServiceDoc(reqId, form);
      }

      if (!mounted) return;
      context.pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Request submitted! Tracking: $trackingNo'), backgroundColor: Colors.green),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Submission failed. Please try again.'), backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_title)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            // Ward selection
            DropdownButtonFormField<int>(
              value: _wardNo,
              decoration: const InputDecoration(labelText: 'Ward No. *', prefixIcon: Icon(Icons.location_city)),
              items: List.generate(7, (i) => DropdownMenuItem(value: i+1, child: Text('Ward ${i+1}'))),
              onChanged: (v) => setState(() => _wardNo = v),
              validator: (v) => v == null ? 'Select your ward' : null,
            ),
            const SizedBox(height: 16),

            // Dynamic form fields
            ..._fields.map((f) => Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: TextFormField(
                keyboardType: f.keyboard,
                maxLines: f.keyboard == TextInputType.multiline ? 4 : 1,
                decoration: InputDecoration(labelText: f.label),
                validator: f.required ? (v) => v!.isEmpty ? '${f.label} is required' : null : null,
                onSaved: (v) => _formData[f.key] = v,
              ),
            )),

            // Document upload
            Text('Supporting Documents', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8, runSpacing: 8,
              children: [
                ..._docs.map((f) => Chip(
                  label: Text(f.path.split('/').last, overflow: TextOverflow.ellipsis),
                  deleteIcon: const Icon(Icons.close, size: 16),
                  onDeleted: () => setState(() => _docs.remove(f)),
                )),
                ActionChip(
                  avatar: const Icon(Icons.attach_file),
                  label: const Text('Add Document'),
                  onPressed: () async {
                    final result = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: ['pdf','jpg','png']);
                    if (result != null) setState(() => _docs.add(File(result.files.single.path!)));
                  },
                ),
              ],
            ),
            const SizedBox(height: 32),

            ElevatedButton.icon(
              onPressed: _submitting ? null : _submit,
              icon: _submitting ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.send),
              label: Text(_submitting ? 'Submitting...' : 'Submit Request'),
            ),
          ]),
        ),
      ),
    );
  }
}

class _FormField {
  final String key, label; final TextInputType keyboard; final bool required;
  const _FormField(this.key, this.label, this.keyboard, this.required);
}

// ══════════════════════════════════════════════
//  COMPLAINTS SCREEN
// ══════════════════════════════════════════════
class ComplaintsScreen extends StatefulWidget {
  const ComplaintsScreen({super.key});
  @override State<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends State<ComplaintsScreen> {
  List<dynamic> _complaints = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    try {
      final res = await getIt<ApiService>().getMyComplaints();
      setState(() { _complaints = res.data['data'] ?? []; _loading = false; });
    } catch (_) { setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Complaints')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/complaints/new'),
        icon: const Icon(Icons.add),
        label: const Text('New Complaint'),
        backgroundColor: AppTheme.primaryColor,
      ),
      body: _loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: _complaints.isEmpty
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.report_outlined, size: 64, color: Colors.grey),
                  const SizedBox(height: 16),
                  const Text('No complaints submitted', style: TextStyle(color: Colors.grey)),
                  const SizedBox(height: 8),
                  ElevatedButton(onPressed: () => context.push('/complaints/new'), child: const Text('File a Complaint')),
                ]))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _complaints.length,
                  itemBuilder: (ctx, i) => _ComplaintCard(complaint: _complaints[i]),
                ),
          ),
    );
  }
}

class _ComplaintCard extends StatelessWidget {
  final Map<String,dynamic> complaint;
  const _ComplaintCard({required this.complaint});

  Color get _statusColor => switch (complaint['status']) {
    'resolved' || 'closed' => Colors.green,
    'in_progress'          => Colors.blue,
    'under_review'         => Colors.orange,
    _                      => Colors.grey,
  };

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: _statusColor.withOpacity(0.1),
          child: Icon(Icons.report, color: _statusColor, size: 20),
        ),
        title: Text(complaint['title'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text('${complaint['tracking_no']} • ${complaint['category']?.toString().replaceAll('_',' ').toUpperCase()}',
          style: const TextStyle(fontSize: 11)),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: _statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(20)),
          child: Text(complaint['status'].toString().replaceAll('_',' ').toUpperCase(),
            style: TextStyle(color: _statusColor, fontSize: 10, fontWeight: FontWeight.bold)),
        ),
        onTap: () => context.push('/complaints/${complaint['tracking_no']}'),
      ),
    );
  }
}

// ══════════════════════════════════════════════
//  New Complaint Screen
// ══════════════════════════════════════════════
class NewComplaintScreen extends StatefulWidget {
  const NewComplaintScreen({super.key});
  @override State<NewComplaintScreen> createState() => _NewComplaintScreenState();
}

class _NewComplaintScreenState extends State<NewComplaintScreen> {
  final _formKey      = GlobalKey<FormState>();
  final _titleCtrl    = TextEditingController();
  final _descCtrl     = TextEditingController();
  final _locationCtrl = TextEditingController();
  String? _category;
  int?    _wardNo;
  double? _lat, _lng;
  bool    _submitting = false;
  bool    _fetchingLocation = false;
  final List<File> _photos = [];

  static const _categories = [
    'road','water_supply','electricity','sanitation',
    'public_service','corruption','environment','other',
  ];

  Future<void> _getLocation() async {
    setState(() => _fetchingLocation = true);
    try {
      final perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) await Geolocator.requestPermission();
      final pos = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      setState(() { _lat = pos.latitude; _lng = pos.longitude; });
    } catch (_) {}
    setState(() => _fetchingLocation = false);
  }

  Future<void> _addPhoto() async {
    final picker = ImagePicker();
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (_) => Column(mainAxisSize: MainAxisSize.min, children: [
        ListTile(leading: const Icon(Icons.camera_alt), title: const Text('Camera'), onTap: () => Navigator.pop(context, ImageSource.camera)),
        ListTile(leading: const Icon(Icons.photo_library), title: const Text('Gallery'), onTap: () => Navigator.pop(context, ImageSource.gallery)),
      ]),
    );
    if (source == null) return;
    final xFile = await picker.pickImage(source: source, maxWidth: 1280, imageQuality: 70);
    if (xFile != null) setState(() => _photos.add(File(xFile.path)));
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);

    try {
      final res = await getIt<ApiService>().submitComplaint({
        'category':             _category,
        'title':                _titleCtrl.text.trim(),
        'description':          _descCtrl.text.trim(),
        'ward_no':              _wardNo,
        'latitude':             _lat,
        'longitude':            _lng,
        'location_description': _locationCtrl.text.trim(),
      });

      final complaintId = res.data['complaint']['id'];
      final trackingNo  = res.data['tracking_no'];

      if (_photos.isNotEmpty) {
        final form = FormData.fromMap({
          'photos': await Future.wait(_photos.map((f) async =>
              MultipartFile.fromFileSync(f.path, filename: f.path.split('/').last))),
        });
        await getIt<ApiService>().uploadComplaintPhotos(complaintId, form);
      }

      if (!mounted) return;
      context.pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Complaint submitted! Tracking: $trackingNo'), backgroundColor: Colors.green),
      );
    } catch (_) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Submission failed. Please try again.'), backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('File a Complaint')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            DropdownButtonFormField<String>(
              value: _category,
              decoration: const InputDecoration(labelText: 'Category *', prefixIcon: Icon(Icons.category)),
              items: _categories.map((c) => DropdownMenuItem(value: c, child: Text(c.replaceAll('_',' ').toUpperCase()))).toList(),
              onChanged: (v) => setState(() => _category = v),
              validator: (v) => v == null ? 'Select a category' : null,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<int>(
              value: _wardNo,
              decoration: const InputDecoration(labelText: 'Ward No. *', prefixIcon: Icon(Icons.location_city)),
              items: List.generate(7, (i) => DropdownMenuItem(value: i+1, child: Text('Ward ${i+1}'))),
              onChanged: (v) => setState(() => _wardNo = v),
              validator: (v) => v == null ? 'Select ward' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _titleCtrl,
              decoration: const InputDecoration(labelText: 'Title *', prefixIcon: Icon(Icons.title)),
              validator: (v) => v!.isEmpty ? 'Title required' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _descCtrl,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Description *', alignLabelWithHint: true),
              validator: (v) => v!.isEmpty ? 'Description required' : null,
            ),
            const SizedBox(height: 16),

            // Location
            Row(children: [
              Expanded(child: TextFormField(
                controller: _locationCtrl,
                decoration: const InputDecoration(labelText: 'Location Description', prefixIcon: Icon(Icons.place)),
              )),
              const SizedBox(width: 8),
              ElevatedButton.icon(
                onPressed: _fetchingLocation ? null : _getLocation,
                icon: _fetchingLocation
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.my_location, size: 18),
                label: const Text('GPS'),
                style: ElevatedButton.styleFrom(minimumSize: const Size(80, 52)),
              ),
            ]),
            if (_lat != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text('📍 ${_lat!.toStringAsFixed(5)}, ${_lng!.toStringAsFixed(5)}',
                  style: const TextStyle(fontSize: 12, color: Colors.green)),
              ),
            const SizedBox(height: 16),

            // Photos
            Text('Photos (up to 5)', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8, runSpacing: 8,
              children: [
                ..._photos.map((f) => Stack(children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(f, width: 80, height: 80, fit: BoxFit.cover),
                  ),
                  Positioned(top: 2, right: 2, child: GestureDetector(
                    onTap: () => setState(() => _photos.remove(f)),
                    child: Container(
                      decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                      child: const Icon(Icons.close, color: Colors.white, size: 16),
                    ),
                  )),
                ])),
                if (_photos.length < 5)
                  InkWell(
                    onTap: _addPhoto,
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      width: 80, height: 80,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey[300]!, style: BorderStyle.solid),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(Icons.add_a_photo, color: Colors.grey),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 32),

            ElevatedButton.icon(
              onPressed: _submitting ? null : _submit,
              icon: _submitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Icon(Icons.send),
              label: Text(_submitting ? 'Submitting...' : 'Submit Complaint'),
            ),
          ]),
        ),
      ),
    );
  }
}
