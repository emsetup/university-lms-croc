# Osec и rpm в типичных путях (лабораторная М7).
case ":$PATH:" in
  *:/usr/sbin:*) ;;
  *) PATH="/usr/sbin:/sbin:$PATH" ;;
esac
export PATH
