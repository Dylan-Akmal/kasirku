import 'package:flutter/material.dart';

class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.voided});

  final bool voided;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: voided ? Colors.red.shade50 : Colors.green.shade50,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        voided ? 'Dibatalkan' : 'Selesai',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: voided ? Colors.red.shade800 : Colors.green.shade800,
        ),
      ),
    );
  }
}