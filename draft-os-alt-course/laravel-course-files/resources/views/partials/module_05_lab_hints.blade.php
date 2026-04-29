{{-- Подсказки после первой неполной автопроверки практики модуля 5 --}}
<div class="card" style="margin:0 0 1rem;padding:0.75rem 1rem;border-left:4px solid #2d6a9f;background:#f3f8fc">
    <p style="margin:0 0 0.5rem;font-size:0.95rem"><strong>Подсказки (модуль 5).</strong> Кратко по заданиям:</p>
    <ul style="margin:0;padding-left:1.2rem;line-height:1.55;font-size:0.92rem">
        <li><strong>Задание 1.</strong> Файлы <code>/etc/net/ifaces/eth0/</code>: исправьте <code>ipv4route</code> на <code>default via 10.0.0.1</code>, затем <code>sudo ifdown eth0</code> и <code>sudo ifup eth0</code> (или <code>sudo systemctl restart network</code>).</li>
        <li><strong>Задание 2.</strong> Заполните <code>eth1</code>: <code>ipv4address</code>, <code>ipv4route</code>, <code>resolv.conf</code>, затем <code>sudo ifup eth1</code>.</li>
        <li><strong>Задание 3.</strong> Сравните <code>ping 172.17.0.1</code> и <code>ping gateway</code>: порядок в <code>/etc/nsswitch.conf</code> для <code>hosts</code> и строка в <code>/etc/hosts</code> для имени <code>gateway</code>.</li>
        <li><strong>Задание 4.</strong> В <code>eth0/options</code>: <code>NM_CONTROLLED=no</code>, согласованные <code>BOOTPROTO</code> / <code>CONFIG_IPV4</code>; при запущенном вручную NetworkManager — завершите процесс.</li>
    </ul>
</div>
