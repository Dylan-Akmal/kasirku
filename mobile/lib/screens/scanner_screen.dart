import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../core/errors.dart';
import '../core/format.dart';
import '../providers/cart_provider.dart';
import '../services/product_service.dart';

enum _Tone { info, success, error }

class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    formats: const [
      BarcodeFormat.ean13,
      BarcodeFormat.ean8,
      BarcodeFormat.upcA,
      BarcodeFormat.upcE,
      BarcodeFormat.code128,
      BarcodeFormat.code39,
    ],
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );

  bool _torchOn = false;
  bool _busy = false;
  String? _lastCode;
  DateTime _lastScanAt = DateTime.fromMillisecondsSinceEpoch(0);

  String _message = 'Arahkan kamera ke barcode produk.';
  _Tone _tone = _Tone.info;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _show(String message, _Tone tone) {
    if (!mounted) return;

    setState(() {
      _message = message;
      _tone = tone;
    });
  }

  Future<void> _toggleTorch() async {
    await _controller.toggleTorch();

    if (mounted) setState(() => _torchOn = !_torchOn);
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy) return;

    final code = capture.barcodes.isEmpty ? null : capture.barcodes.first.rawValue;
    if (code == null || code.isEmpty) return;

    final now = DateTime.now();
    final isRepeat =
        code == _lastCode && now.difference(_lastScanAt) < const Duration(milliseconds: 2000);

    if (isRepeat) return;

    _lastCode = code;
    _lastScanAt = now;
    _busy = true;

    HapticFeedback.mediumImpact();

    try {
      final product = await ProductService.findBySku(code);

      if (!mounted) return;

      if (product == null) {
        _show('Barcode $code tidak ditemukan.', _Tone.error);
      } else {
        final warning = context.read<CartProvider>().add(product);

        _show(
          warning ?? '${product.name} ditambahkan.',
          warning == null ? _Tone.success : _Tone.error,
        );
      }
    } catch (e) {
      _show(errorMessage(e, 'Gagal mencari produk.'), _Tone.error);
    } finally {
      _busy = false;
    }
  }

  Color get _toneBackground {
    switch (_tone) {
      case _Tone.info:
        return Colors.blue.shade50;
      case _Tone.success:
        return Colors.green.shade50;
      case _Tone.error:
        return Colors.red.shade50;
    }
  }

  Color get _toneForeground {
    switch (_tone) {
      case _Tone.info:
        return Colors.blue.shade900;
      case _Tone.success:
        return Colors.green.shade900;
      case _Tone.error:
        return Colors.red.shade900;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        title: const Text('Scan Barcode'),
        actions: [
          IconButton(
            icon: Icon(_torchOn ? Icons.flash_on : Icons.flash_off),
            tooltip: 'Senter',
            onPressed: _toggleTorch,
          ),
          IconButton(
            icon: const Icon(Icons.cameraswitch),
            tooltip: 'Ganti kamera',
            onPressed: () => _controller.switchCamera(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          Center(
            child: Container(
              width: 280,
              height: 150,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white, width: 2),
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: _bottomPanel(context),
          ),
        ],
      ),
    );
  }

  Widget _bottomPanel(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: _toneBackground,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(_message, style: TextStyle(color: _toneForeground)),
            ),
            const SizedBox(height: 12),
            Consumer<CartProvider>(
              builder: (context, cart, _) => Row(
                children: [
                  Expanded(
                    child: Text(
                      cart.items.isEmpty
                          ? 'Keranjang kosong'
                          : '${cart.itemCount} item · ${rupiah(cart.subtotal)}',
                      style: theme.textTheme.titleSmall,
                    ),
                  ),
                  FilledButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Selesai'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}